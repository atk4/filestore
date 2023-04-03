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
        $app->setResponseHeader('Content-Description', 'File Transfer');
        $app->setResponseHeader('Content-Type', 'application/octet-stream');
        $app->setResponseHeader('Cache-Control', 'must-revalidate');
        $app->setResponseHeader('Expires', '-1');
        $app->setResponseHeader('Content-Disposition', 'attachment; filename="' . $model->get('meta_filename') . '"');
        $app->setResponseHeader('Content-Length', (string) $model->get('meta_size'));
        $app->setResponseHeader('Pragma', 'public');
        $app->setResponseHeader('Accept-Ranges', 'bytes');

        static::output($model, $app);
    }

    /**
     * @return never
     */
    public static function view(File $model, App $app): void
    {
        $app->setResponseHeader('Content-Description', 'File Transfer');
        $app->setResponseHeader('Content-Type', $model->get('meta_mime_type'));
        $app->setResponseHeader('Cache-Control', 'must-revalidate');
        $app->setResponseHeader('Expires', '-1');
        $app->setResponseHeader('Content-Disposition', 'inline; filename="' . $model->get('meta_filename') . '"');
        $app->setResponseHeader('Content-Length', (string) $model->get('meta_size'));
        $app->setResponseHeader('Pragma', 'public');
        $app->setResponseHeader('Accept-Ranges', 'bytes');

        static::output($model, $app);
    }

    /**
     * @return never
     */
    protected static function output(File $model, App $app): void
    {
        $path = $model->get('location');

        // TODO support streaming
        // fpassthru($model->flysystem->readStream($path));
        $app->terminate($model->flysystem->read($path));
    }
}
