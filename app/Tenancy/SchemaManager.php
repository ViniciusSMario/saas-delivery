<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\InvalidTenantSchema;
use App\Tenancy\Exceptions\TenantSchemaMissing;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar do código que escreve SQL de schema ou mexe no search_path.
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
    }

    public function drop(string $schema): void
    {
        $this->central()->statement('drop schema if exists '.$this->quote($schema).' cascade');
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

        // Preparado, não ativado: com config('tenancy.database_roles') ligado, aqui entraria
        // um SET ROLE para um role com permissão apenas neste schema.
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
