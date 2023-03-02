<?php

declare(strict_types=1);

namespace Atk4\Filestore;

use Atk4\Filestore\Model\File;
use Atk4\Ui\App;

class Helper
{
    /**
     * @return never
     */
    public static function download(File $model, App $app): void
    {
        $headers = [
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'must-revalidate',
            'Expires' => '-1',
            'Content-Disposition' => 'attachment; filename="' . $model->get('meta_filename') . '"',
            'Content-Length' => (string) $model->get('meta_size'),
            'Pragma' => 'public',
            'Accept-Ranges' => 'bytes',
        ];

        static::output($model, $headers, $app);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return never
     */
    protected static function output(File $model, array $headers, App $app): void
    {
        $path = $model->get('location');

        // TODO support streaming
        // fpassthru($model->flysystem->readStream($path));
        $app->terminate($model->flysystem->read($path), $headers);
    }

    /**
     * @return never
     */
    public static function view(File $model, App $app): void
    {
        $headers = [
            'Content-Description' => 'File Transfer',
            'Content-Type' => $model->get('meta_mime_type'),
            'Cache-Control' => 'must-revalidate',
            'Expires' => '-1',
            'Content-Disposition' => 'inline; filename="' . $model->get('meta_filename') . '"',
            'Content-Length' => (string) $model->get('meta_size'),
            'Pragma' => 'public',
            'Accept-Ranges' => 'bytes',
        ];

        static::output($model, $headers, $app);
    }
}
