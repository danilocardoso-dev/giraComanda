<?php

namespace App\Http\Controllers;

use App\Actions\CreateDatabaseBackup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function index(): View
    {
        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $directory = trim((string) config('backup.directory', 'backups/database'), '/');
        $backups = collect($disk->files($directory))
            ->filter(fn (string $path): bool => $this->isValidBackupName(basename($path)))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => $disk->size($path),
                'created_at' => Carbon::createFromTimestamp($disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return view('backups.index', compact('backups'));
    }

    public function store(CreateDatabaseBackup $createDatabaseBackup): RedirectResponse
    {
        try {
            $backupPath = $createDatabaseBackup->handle();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('platform.backups.index')
                ->with('error', 'Não foi possível criar o backup. Verifique se o MySQL do XAMPP está em execução.');
        }

        return redirect()
            ->route('platform.backups.index')
            ->with('success', 'Backup criado com sucesso: '.basename($backupPath));
    }

    public function download(string $filename): StreamedResponse
    {
        abort_unless($this->isValidBackupName($filename), 404);

        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $directory = trim((string) config('backup.directory', 'backups/database'), '/');
        $backupPath = "{$directory}/{$filename}";

        abort_unless($disk->exists($backupPath), 404);

        return $disk->download($backupPath, $filename, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/sql',
        ]);
    }

    private function isValidBackupName(string $filename): bool
    {
        return preg_match('/\Agiracomanda-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d+)?\.sql\z/', $filename) === 1;
    }
}
