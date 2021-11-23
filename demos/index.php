<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Persistence;
use Atk4\Filestore\Helper;
use Atk4\Filestore\Model\File;
use Atk4\Ui\Callback;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\JsExpression;
use League\Flysystem\Filesystem;

require __DIR__ . '/init-autoloader.php';

// specify folder where files will be actually stored
$adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(__DIR__ . '/localfiles');
$filesystem = new Filesystem($adapter);

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

$col = Columns::addTo($app);

$form = Form::addTo($col->addColumn());
$form->setModel(
    new Friend($app->db, [
        'filesystem' => $filesystem,
    ])
);
$form->model->tryLoad(1);

$gr = \Atk4\Ui\Grid::addTo($col->addColumn(), [
    'menu' => false,
    'paginator' => false,
]);
$gr->setModel(new File($app->db));

$form->onSubmit(function (Form $form) use ($gr) {
    $form->model->save();

    return [
        $gr->jsReload(),
    ];
});

\Atk4\Ui\View::addTo($app, ['ui' => 'divider']);

$crud = \Atk4\Ui\Crud::addTo($app);
$crud->setModel(new Friend($app->db, ['filesystem' => $filesystem]));

\Atk4\Ui\View::addTo($app, ['ui' => 'divider']);

$callback_download = Callback::addTo($app);
$callback_download->set(function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = File::assertInstanceOf($model->load($id)->ref('file'));
    Helper::download($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'download'],
    new JsExpression(
        'document.location = "' . $callback_download->getJsUrl() . '&row_id="+[]',
        [$crud->table->jsRow()->data('id')]
    )
);

$callback_view = Callback::addTo($app);
$callback_view->set(function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = File::assertInstanceOf($model->load($id)->ref('file'));
    Helper::view($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'image'],
    new JsExpression(
        'document.location = "' . $callback_view->getJsUrl() . '&row_id="+[]',
        [$crud->table->jsRow()->data('id')]
    )
);
