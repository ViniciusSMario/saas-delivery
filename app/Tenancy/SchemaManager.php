<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\InvalidTenantSchema;
use App\Tenancy\Exceptions\TenantSchemaMissing;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar do código que escreve SQL de schema, de papel (SET ROLE) ou mexe no search_path.
 */
final class SchemaManager
{
    public const PATTERN = '/^tenant_[a-z0-9]{6,40}$/';

    /** Schema inexistente usado quando não há tenant ativo (não passa na regex, então nunca é criado). */
    public const NONE = 'tenant_none';

    public const EXTENSIONS = 'extensions';

    public static function isValid(string $schema): bool
    {
        return preg_match(self::PATTERN, $schema) === 1;
    }

    public function create(string $schema): void
    {
        $this->central()->statement('create schema '.$this->quote($schema));

        if (self::rolesEnabled()) {
            $this->assignToRole($schema);
        }
    }

    /** @param  bool  $withRole  false na restauração: o papel continua sendo do schema restaurado */
    public function drop(string $schema, bool $withRole = true): void
    {
        $this->central()->statement('drop schema if exists '.$this->quote($schema).' cascade');

        if ($withRole && $this->roleExists($schema)) {
            $role = $this->quoteRole($schema);
            $this->central()->statement("drop owned by {$role}");
            $this->central()->statement("drop role {$role}");
        }
    }

    /*
     * Papel do PostgreSQL por tenant (config tenancy.database_roles). O papel é dono do
     * schema e de tudo nele; a aplicação é membro e, ao ativar o tenant, faz SET ROLE.
     * A partir daí o próprio banco recusa ler outro schema ou as tabelas da plataforma.
     */

    public static function rolesEnabled(): bool
    {
        return (bool) config('tenancy.database_roles');
    }

    public static function roleName(string $schema): string
    {
        return $schema.'_role';
    }

    public function roleExists(string $schema): bool
    {
        $this->assertValid($schema);

        return (bool) $this->central()->scalar('select 1 from pg_roles where rolname = ?', [self::roleName($schema)]);
    }

    /** Cria (se faltar) o papel do tenant e passa para ele o schema e todos os objetos. Idempotente. */
    public function assignToRole(string $schema): void
    {
        $role = $this->quoteRole($schema);

        if (! $this->roleExists($schema)) {
            $this->central()->statement("create role {$role} nologin noinherit");
        }

        // A aplicação precisa poder agir como o papel (SET) e usar o que é dele (INHERIT).
        $this->central()->statement("grant {$role} to current_user with inherit true, set true");
        $this->central()->statement('alter schema '.$this->quote($schema)." owner to {$role}");

        $name = self::roleName($schema);
        $this->central()->unprepared(<<<SQL
            do $$
            declare r record;
            begin
                for r in select c.relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
                         where n.nspname = '{$schema}' and c.relkind in ('r', 'p', 'v')
                loop
                    execute format('alter table %I.%I owner to %I', '{$schema}', r.relname, '{$name}');
                end loop;
                -- Sequências soltas (as de colunas id acompanham a tabela).
                for r in select c.relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
                         where n.nspname = '{$schema}' and c.relkind = 'S'
                           and not exists (select 1 from pg_depend d where d.objid = c.oid and d.deptype in ('a', 'i'))
                loop
                    execute format('alter sequence %I.%I owner to %I', '{$schema}', r.relname, '{$name}');
                end loop;
            end
            $$;
            SQL);
    }

    /** @return list<string> papéis de tenant que não têm mais schema (sobras) */
    public function orphanRoles(): array
    {
        $schemas = $this->all();

        return $this->central()->table('pg_roles')->where('rolname', 'like', 'tenant\_%\_role')->pluck('rolname')
            ->reject(fn (string $role) => in_array(substr($role, 0, -5), $schemas, true))
            ->values()->all();
    }

    public function dropOrphanRole(string $role): void
    {
        $schema = substr($role, 0, -5);
        $this->assertValid($schema);

        if (! $this->exists($schema) && $this->roleExists($schema)) {
            $quoted = $this->quoteRole($schema);
            $this->central()->statement("drop owned by {$quoted}");
            $this->central()->statement("drop role {$quoted}");
        }
    }

    /** Usado na restauração de backup: o schema atual sai do caminho antes do dump voltar. */
    public function rename(string $from, string $to): void
    {
        $this->central()->statement('alter schema '.$this->quote($from).' rename to '.$this->quote($to));
    }

    public function exists(string $schema): bool
    {
        return $this->central()->table('information_schema.schemata')
            ->where('schema_name', $schema)
            ->exists();
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->central()->table('information_schema.schemata')
            ->where('schema_name', 'like', 'tenant\_%')
            ->orderBy('schema_name')
            ->pluck('schema_name')
            ->filter(fn (string $name) => self::isValid($name))
            ->values()
            ->all();
    }

    /**
     * Reconecta a conexão "tenant" apontando só para o schema do tenant (+ extensions).
     * Nunca inclui public: tabela faltando deve quebrar, não cair na plataforma.
     */
    public function switchTo(string $schema): void
    {
        $this->assertValid($schema);

        config(['database.connections.tenant.search_path' => $schema.','.self::EXTENSIONS]);
        DB::purge('tenant');

        // O PostgreSQL aceita schema inexistente no search_path; aqui isso vira erro.
        $current = DB::connection('tenant')->scalar('select current_schema()');

        if ($current !== $schema) {
            $this->reset();

            throw new TenantSchemaMissing($schema);
        }

        // Papel por tenant: daqui em diante a conexão só tem os direitos dele.
        if (self::rolesEnabled()) {
            try {
                DB::connection('tenant')->statement('set role '.$this->quoteRole($schema));
            } catch (QueryException $e) {
                $this->reset();

                throw new TenantSchemaMissing($schema);
            }
        }
    }

    /** Estado fechado: a conexão aponta para um schema que não existe. */
    public function reset(): void
    {
        config(['database.connections.tenant.search_path' => self::NONE]);
        DB::purge('tenant');
    }

    public function current(): ?string
    {
        return DB::connection('tenant')->scalar('select current_schema()');
    }

    private function quote(string $schema): string
    {
        $this->assertValid($schema);

        return '"'.$schema.'"';
    }

    private function quoteRole(string $schema): string
    {
        $this->assertValid($schema);

        return '"'.self::roleName($schema).'"';
    }

    private function assertValid(string $schema): void
    {
        if (! self::isValid($schema)) {
            throw new InvalidTenantSchema($schema);
        }
    }

    private function central(): Connection
    {
        return DB::connection('central');
    }
}
