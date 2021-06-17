<?php

declare(strict_types=1);

namespace Atk4\Filestore;

use Atk4\Filestore\Model\File;
use Atk4\Ui\App;

class Helper
{
    public static function download(File $model, App $app = null)
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

    private static function output(File $model, array $headers, App $app)
    {
        $location = $model->get('location');

        if ($app !== null) {
            $app->terminate($model->flysystem->get($location)->read(), $headers);
        }

        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }

        fpassthru($model->flysystem->readStream($location));

        exit;
    }

    public static function view(File $model, App $app = null)
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
