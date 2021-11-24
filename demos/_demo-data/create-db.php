<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\Migrator;

require_once __DIR__ . '/../init-autoloader.php';

$sqliteFile = __DIR__ . '/db.sqlite';
if (!file_exists($sqliteFile)) {
    new Persistence\Sql('sqlite:' . $sqliteFile);
}
unset($sqliteFile);

/** @var Persistence\Sql $db */
require_once __DIR__ . '/../init-db.php';

$model = new Model($db, ['table' => 'filestore_file']);
$model->addField('token', ['required' => true]);
$model->addField('location', ['type' => 'text']);
$model->addField('url', ['type' => 'text']);
$model->addField('storage');
$model->addField('status');
$model->addField('source_file_id', ['type' => 'integer']);
$model->addField('meta_filename');
$model->addField('meta_extension');
$model->addField('meta_md5');
$model->addField('meta_mime_type');
$model->addField('meta_size', ['type' => 'integer']);
$model->addField('meta_is_image', ['type' => 'boolean']);
$model->addField('meta_image_width', ['type' => 'integer']);
$model->addField('meta_image_height', ['type' => 'integer']);

(new Migrator($model))->create();

$model = new Model($db, ['table' => 'friend']);
$model->addField('name', ['required' => true]);
$model->addField('file_id', ['type' => 'integer']);
$model->addField('file', ['type' => 'text']);
$model->addField('file2', ['type' => 'text']);

(new Migrator($model))->create();

echo 'import complete!' . "\n\n";
