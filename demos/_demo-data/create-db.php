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

$fileModel = new Model($db, ['table' => 'filestore_file']);
$fileModel->addField('token', ['required' => true]);
$fileModel->addField('location', ['type' => 'text']);
$fileModel->addField('source_file_id', ['type' => 'integer']);
$fileModel->addField('status');
$fileModel->addField('created_at', ['type' => 'datetime', 'required' => true]);
$fileModel->addField('meta_filename');
$fileModel->addField('meta_extension');
$fileModel->addField('meta_md5');
$fileModel->addField('meta_mime_type');
$fileModel->addField('meta_size', ['type' => 'integer']);
$fileModel->addField('meta_is_image', ['type' => 'boolean']);
$fileModel->addField('meta_image_width', ['type' => 'integer']);
$fileModel->addField('meta_image_height', ['type' => 'integer']);

(new Migrator($fileModel))->create();

$friendModel = new Model($db, ['table' => 'friend']);
$friendModel->addField('name', ['required' => true]);
$friendModel->addField('file');
$friendModel->addField('file2');

(new Migrator($friendModel))->create();

$friendModel->hasOne('file', ['model' => $fileModel, 'theirField' => 'token']);
$friendModel->hasOne('file2', ['model' => $fileModel, 'theirField' => 'token']);
(new Migrator($db))->createForeignKey($friendModel->getReference('file'));
(new Migrator($db))->createForeignKey($friendModel->getReference('file2'));

echo 'import complete!' . "\n\n";
