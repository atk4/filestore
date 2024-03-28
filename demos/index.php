<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Persistence;
use Atk4\Filestore\Helper;
use Atk4\Filestore\Model\File;
use Atk4\Ui\App;
use Atk4\Ui\Callback;
use Atk4\Ui\Crud;
use Atk4\Ui\Exception;
use Atk4\Ui\Form;
use Atk4\Ui\Grid;
use Atk4\Ui\Header;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\Layout\Centered;
use Atk4\Ui\Tabs;
use Atk4\Ui\View;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

require __DIR__ . '/init-autoloader.php';

// init App
$app = new App(['title' => 'Filestore Demo']);
$app->initLayout([Centered::class]);

// init db
try {
    /** @var Persistence\Sql $db */
    require_once __DIR__ . '/init-db.php';
    $app->db = $db;
    unset($db);
} catch (\Throwable $e) {
    throw new Exception('Database error: ' . $e->getMessage());
}

// specify folder where files will be actually stored
$adapter = new LocalFilesystemAdapter(__DIR__ . '/_demo-data/localfiles');
$filesystem = new Filesystem($adapter);

// setup tabs
// @todo make tab 2 dynamic
$tabs = Tabs::addTo($app);
$t1 = $tabs->addTab('Friends');
$t2 = $tabs->addTab('Filestore Files');

// new friend form
Header::addTo($t1, ['Add New Friend']);
$form = Form::addTo($t1);
$form->setModel(
    (new Friend($app->db, [
        'filesystem' => $filesystem,
    ]))->createEntity()
);

$form->onSubmit(static function (Form $form) use ($app) {
    $form->model->save();

    return $app->layout->jsReload();
});

View::addTo($t1, ['ui' => 'divider']);

// CRUD with all Friends records
Header::addTo($t1, ['All Friends']);
$crud = Crud::addTo($t1);
$crud->setModel(new Friend($app->db, ['filesystem' => $filesystem]));

// custom actions
$callbackDownload = Callback::addTo($app);
$callbackDownload->set(static function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = File::assertInstanceOf($model->load($id)->ref('file1'));
    Helper::download($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'download'],
    new JsExpression(
        'document.location = [] + \'&row_id=\' + []',
        [$callbackDownload->getJsUrl(), $crud->table->jsRow()->data('id')]
    )
);

$callbackView = Callback::addTo($app);
$callbackView->set(static function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = File::assertInstanceOf($model->load($id)->ref('file1'));
    Helper::view($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'image'],
    new JsExpression(
        'document.location = [] + \'&row_id=\' + []',
        [$callbackView->getJsUrl(), $crud->table->jsRow()->data('id')]
    )
);

// list all filestore files
$gr = Grid::addTo($t2, [
    'paginator' => false,
]);
$files = new File($app->db, ['flysystem' => $filesystem]);
$gr->menu->addItem('Cleanup Drafts')->on('click', static function () use ($gr, $files) {
    $files->cleanupDrafts();

    return $gr->jsReload();
});
$gr->setModel($files);
