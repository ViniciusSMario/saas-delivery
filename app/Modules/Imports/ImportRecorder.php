<?php

namespace App\Modules\Imports;

use App\Modules\Access\Models\User;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Imports\Models\DataImportItem;

/** Abre o registro de uma importação e anota cada registro criado ou alterado (com o "antes"). */
class ImportRecorder
{
    public DataImport $import;

    public function start(string $kind, string $fileName, string $mode, ?User $user, array $summary = [], ?string $photosName = null): DataImport
    {
        return $this->import = DataImport::create([
            'kind' => $kind,
            'user_id' => $user?->id,
            'file_name' => mb_substr($fileName, 0, 200),
            'photos_file_name' => $photosName ? mb_substr($photosName, 0, 200) : null,
            'mode' => $mode,
            'status' => 'applied',
            'summary' => $summary,
        ]);
    }

    public function track(string $type, int $id, string $action, ?array $before = null): void
    {
        DataImportItem::create([
            'data_import_id' => $this->import->id,
            'subject_type' => $type,
            'subject_id' => $id,
            'action' => $action,
            'before' => $before,
        ]);
    }
}
