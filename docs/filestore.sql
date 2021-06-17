CREATE TABLE `filestore_file`
(
    `id`                int(11) unsigned NOT NULL AUTO_INCREMENT,
    `token`             varchar(60)  DEFAULT NULL,
    `location`          varchar(400) DEFAULT NULL,
    `url`               varchar(400) DEFAULT NULL,
    `storage`           varchar(50)  DEFAULT NULL,
    `status`            varchar(10)  DEFAULT NULL,
    `source_file_id`    int(11) DEFAULT NULL,
    `meta_filename`     varchar(250) DEFAULT NULL,
    `meta_extension`    varchar(10)  DEFAULT NULL,
    `meta_md5`          varchar(60)  DEFAULT NULL,
    `meta_mime_type`    varchar(20)  DEFAULT NULL,
    `meta_size`         int(11) DEFAULT NULL,
    `meta_is_image`     tinyint(1) DEFAULT NULL,
    `meta_image_width`  int(11) DEFAULT NULL,
    `meta_image_height` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY                 `file_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
