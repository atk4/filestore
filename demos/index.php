<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Filestore\Helper;
use Atk4\Filestore\Model\File;
use Atk4\Ui\Callback;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\Header;
use Atk4\Ui\Js\JsBlock;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\View;
use League\Flysystem\Filesystem;

require __DIR__ . '/init-autoloader.php';

// init App
$app = new \Atk4\Ui\App(['title' => 'Filestore Demo']);
$app->initLayout([\Atk4\Ui\Layout\Centered::class]);

// init db
try {
    /** @var Persistence\Sql $db */
    require_once __DIR__ . '/init-db.php';
    $app->db = $db;
    unset($db);
} catch (\Throwable $e) {
    throw new \Atk4\Ui\Exception('Database error: ' . $e->getMessage());
}

// specify folder where files will be actually stored
$adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(__DIR__ . '/_demo-data/localfiles');
$filesystem = new Filesystem($adapter);

$col = Columns::addTo($app);

// New friend form
$c1 = $col->addColumn()->setStyle('border', '1px solid gray');

Header::addTo($c1, ['Add New Friend']);
$form = Form::addTo($c1);
$form->setModel(
    (new Friend($app->db, [
        'filesystem' => $filesystem,
    ]))->createEntity()
);
$form->onSubmit(function (Form $form) use ($app) {
    $form->model->save();

    return new JsBlock([
        $app->layout->jsReload(),
    ]);
});

// Grid with all filestore files
$c2 = $col->addColumn()->setStyle('border', '1px solid gray');

Header::addTo($c2, ['All Filestore Files']);
$gr = \Atk4\Ui\Crud::addTo($c2, [
    //'menu' => false,
    'paginator' => false,
]);
$files = new File($app->db);
$files->removeUserAction('add');
$files->removeUserAction('edit');
$files->addUserAction('cleanup_drafts', [
    'callback' => function($m) use ($gr) {
        $m->cleanupDrafts();
        //return $gr->jsReload(); // @todo this way it's impossible
        return 'Draft files are deleted.';
    },
    'appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS,
    'description' => 'Cleanup Drafts',
]);
$gr->setModel($files);

View::addTo($app, ['ui' => 'divider']);

// CRUD with all Friends records
Header::addTo($app, ['All Friends']);
$crud = \Atk4\Ui\Crud::addTo($app);
$crud->setModel(new Friend($app->db, ['filesystem' => $filesystem]));

// custom actions
$callbackDownload = Callback::addTo($app);
$callbackDownload->set(function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = File::assertInstanceOf($model->load($id)->ref('file'));
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
$callbackView->set(function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = File::assertInstanceOf($model->load($id)->ref('file'));
    Helper::view($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'image'],
    new JsExpression(
        'document.location = [] + \'&row_id=\' + []',
        [$callbackView->getJsUrl(), $crud->table->jsRow()->data('id')]
    )
);
