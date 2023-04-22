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
    protected static function terminate(File $entity, App $app): void
    {
        $app->terminate($entity->getStream());
    }

    /**
     * @return never
     */
    public static function download(File $entity, App $app): void
    {
        $app->setResponseHeader('Content-Description', 'Download File');
        $app->setResponseHeader('Content-Type', $entity->get('meta_mime_type'));
        $app->setResponseHeader('Cache-Control', 'must-revalidate');
        $app->setResponseHeader('Expires', '-1');
        $app->setResponseHeader('Content-Disposition', 'attachment; filename="' . $entity->get('meta_filename') . '"');
        $app->setResponseHeader('Content-Length', (string) $entity->get('meta_size'));
        $app->setResponseHeader('Pragma', 'public');
        $app->setResponseHeader('Accept-Ranges', 'bytes');

        static::terminate($entity, $app);
    }

    /**
     * @return never
     */
    public static function view(File $entity, App $app): void
    {
        $app->setResponseHeader('Content-Description', 'View File');
        $app->setResponseHeader('Content-Type', $entity->get('meta_mime_type'));
        $app->setResponseHeader('Cache-Control', 'must-revalidate');
        $app->setResponseHeader('Expires', '-1');
        $app->setResponseHeader('Content-Disposition', 'inline; filename="' . $entity->get('meta_filename') . '"');
        $app->setResponseHeader('Content-Length', (string) $entity->get('meta_size'));
        $app->setResponseHeader('Pragma', 'public');
        $app->setResponseHeader('Accept-Ranges', 'bytes');

        static::terminate($entity, $app);
    }
}
