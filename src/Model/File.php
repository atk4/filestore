<?php

declare(strict_types=1);

namespace Atk4\Filestore\Model;

use Atk4\Data\Model;
use League\Flysystem\Filesystem;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamInterface;

class File extends Model
{
    public $table = 'filestore_file';

    public ?string $titleField = 'meta_filename';

    /** All uploaded files first get this status */
    public const STATUS_DRAFT = 'draft';
    /** When file is linked to some other model */
    public const STATUS_LINKED = 'linked';
    /** @const string Used for thumbnail files */
    public const STATUS_THUMB = 'thumb';
    /** @const list<string> */
    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LINKED,
        self::STATUS_THUMB,
    ];

    /** @var Filesystem */
    public $flysystem;

    /** Should we automatically create thumbnail for images */
    public bool $createThumbnail = true;

    /** Thumbnail image max width in pixels */
    protected int $thumbnailMaxWidth = 150;

    /** Thumbnail image max height in pixels */
    protected int $thumbnailMaxHeight = 150;

    /** @var 'png'|'jpg'|'gif' Thumbnail format */
    protected string $thumbnailFormat = 'png';

    /** In seconds, to prevent cleaning up unsaved forms */
    protected int $cleanupDraftsDelay = 2 * 24 * 3600;

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url');

        $this->hasOne('source_file_id', [
            'model' => [static::class],
        ]);

        $this->addField('status', [
            'enum' => self::ALL_STATUSES,
            'default' => self::STATUS_DRAFT,
        ]);
        $this->addField('created_at', ['type' => 'datetime', 'required' => true]);

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

        $this->hasMany('related_files', [
            'model' => [static::class],
            'theirField' => 'source_file_id',
        ]);

        $this->onHookShort(Model::HOOK_BEFORE_SAVE, function (bool $isUpdate) {
            if (!$isUpdate) {
                $this->set('created_at', new \DateTime());
            }
        });

        // change status of thumbs when status of original image changes
        $this->onHookShort(Model::HOOK_AFTER_SAVE, function () {
            if ($this->get('status') === self::STATUS_LINKED) {
                $files = (clone $this->getModel())->addCondition('source_file_id', $this->getId());
                foreach ($files as $file) {
                    $file->set('status', self::STATUS_THUMB);
                    $file->save();
                }
            }
        });

        // cascade-delete all related child files, for example, thumbnails
        $this->onHookShort(Model::HOOK_BEFORE_DELETE, function () {
            $files = (clone $this->getModel())->addCondition('source_file_id', $this->getId());
            foreach ($files as $file) {
                $file->delete();
            }
        });

        // delete physical file from storage after we delete DB record
        $this->onHookShort(Model::HOOK_AFTER_DELETE, function () {
            $path = $this->get('location');
            if ($path && $this->flysystem->fileExists($path)) {
                $this->flysystem->delete($path);
            }
        });
    }

    /**
     * @return static
     */
    public function newFile(): Model
    {
        $this->assertIsModel();

        $entity = $this->createEntity();
        $entity->set('token', uniqid('token-'));
        $entity->set('location', uniqid('file-') . '.bin');

        return $entity;
    }

    /**
     * Create new entity from file path.
     *
     * @param string $path     Path to file to import
     * @param string $fileName Optional original file name
     *
     * @return static
     */
    public function createFromPath(string $path, string $fileName = null): Model
    {
        $this->assertIsModel();

        if ($fileName === null) {
            $fileName = basename($path);
        }

        $entity = $this->newFile();

        // store file in filesystem
        $stream = fopen($path, 'r+');
        $entity->flysystem->writeStream($entity->get('location'), $stream, ['visibility' => 'public']);
        fclose($stream);

        // detect mime-type
        $detector = new FinfoMimeTypeDetector();
        $mimeType = $detector->detectMimeTypeFromFile($path);
        $entity->set('meta_mime_type', $mimeType);

        // store meta-information
        $entity->set('meta_md5', md5_file($path));
        $entity->set('meta_filename', $fileName);
        $entity->set('meta_size', filesize($path));
        $pos = strrpos($fileName, '.');
        if ($pos !== false) {
            $ext = strtolower(substr($fileName, $pos + 1));
            if ($ext !== 'tmp') {
                $entity->set('meta_extension', $ext);
            }
        }

        // additional meta-information for images
        $imageSizeArr = getimagesize($path);
        $entity->set('meta_is_image', $imageSizeArr !== false);
        if ($imageSizeArr !== false) {
            $entity->set('meta_image_width', $imageSizeArr[0]);
            $entity->set('meta_image_height', $imageSizeArr[1]);
        }

        $entity->save();

        // create thumbnail images if needed and possible
        if ($entity->get('meta_is_image') && $this->createThumbnail) {
            $entity->createThumbnail($path);
        }

        return $entity;
    }

    /**
     * Create thumbnail images of current image.
     *
     * @param string $path Local path to original file
     *
     * @return bool True on success
     */
    public function createThumbnail(string $path): bool
    {
        $this->assertIsEntity();

        $maxWidth = $this->thumbnailMaxWidth;
        $maxHeight = $this->thumbnailMaxHeight;

        $src = imagecreatefromstring(file_get_contents($path));
        if ($src === false) {
            return false; // unsupported image type
        }

        [$width, $height] = getimagesize($path);
        $xRatio = $maxWidth / $width;
        $yRatio = $maxHeight / $height;

        if ($width <= $maxWidth && $height <= $maxHeight) {
            $tnWidth = $width;
            $tnHeight = $height;
        } elseif ($xRatio * $height < $maxHeight) {
            $tnHeight = (int) round($xRatio * $height);
            $tnWidth = $maxWidth;
        } else {
            $tnWidth = (int) round($yRatio * $width);
            $tnHeight = $maxHeight;
        }

        $tmp = imagecreatetruecolor($tnWidth, $tnHeight);

        // check if this image is PNG or GIF, then set if Transparent
        if ($this->thumbnailFormat === 'png' || $this->thumbnailFormat === 'gif') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagefilledrectangle($tmp, 0, 0, $tnWidth, $tnHeight, imagecolorallocatealpha($tmp, 255, 255, 255, 127));
        }
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tnWidth, $tnHeight, $width, $height);

        // create temporary thumb file
        $thumbFile = tmpfile();
        try {
            switch ($this->thumbnailFormat) {
                case 'gif':
                    imagegif($tmp, $thumbFile);

                    break;
                case 'jpg':
                    imagejpeg($tmp, $thumbFile);

                    break;
                case 'png':
                    imagepng($tmp, $thumbFile);

                    break;
                default:
                    return false; // unsupported thumbnail format
            }
            imagedestroy($src);
            imagedestroy($tmp);

            $uri = stream_get_meta_data($thumbFile)['uri'];

            // save thumbnail
            $thumbName = basename($this->get('meta_filename')) . '.thumb' . $this->thumbnailFormat;
            $thumbModel = clone $this->getModel();
            $thumbModel->createThumbnail = false; // do not create thumbnails of thumbnails
            $thumbEntity = $thumbModel->createFromPath($uri, $thumbName);
            $thumbEntity->save(['source_file_id' => $this->getId()]);
        } finally {
            fclose($thumbFile);
        }

        return true;
    }

    public function getStream(): StreamInterface
    {
        $path = $this->get('location');
        $resource = $this->flysystem->readStream($path);
        $stream = (new Psr17Factory())->createStreamFromResource($resource);

        return $stream;
    }

    /**
     * Useful method to clean up all draft files.
     * Can be called as user action or on schedule bases to clean up filestore repository.
     */
    public function cleanupDrafts(): void
    {
        $this->getPersistence()->atomic(function () {
            $files = (clone $this)
                ->addCondition('status', self::STATUS_DRAFT)
                ->addCondition('created_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->cleanupDraftsDelay . 'S')));

            foreach ($files as $file) {
                $file->delete();
            }
        });
    }
}
