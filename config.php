<?php

require_once INCLUDE_DIR . 'class.plugin.php';
use Aws\S3\S3Client;

class DigitalOceanSpacesConfig extends PluginConfig {

    function getOptions() {
        return array(
            'bucket' => new TextboxField(array(
                'label' => 'DigitalOcean Space Name',
                'configuration' => array('size' => 40),
            )),
            'folder' => new TextboxField(array(
                'label' => 'Folder Path within Space',
                'configuration' => array('size' => 40),
            )),
            'region' => new TextboxField(array(
                'label' => 'Region',
                'configuration' => array('size' => 40),
            )),
            'endpoint' => new TextboxField(array(
                'label' => 'Endpoint URL',
                'configuration' => array('size' => 40, 'length' => 100),
            )),
            'access-info' => new SectionBreakField(array(
                'label' => 'Access Information',
            )),
            'access-key-id' => new TextboxField(array(
                'required' => true,
                'configuration' => array('length' => 64, 'size' => 40),
                'label' => 'DigitalOcean Access Key',
            )),
            'secret-access-key' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'required' => true,
                'configuration' => array('length' => 64, 'size' => 40),
                'label' => 'DigitalOcean Secret Key',
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        $credentials = [
            'credentials' => [
                'key' => $config['access-key-id'],
                'secret' => $config['secret-access-key'],
            ],
            'version' => 'latest',
            'region' => $config['region'],
            'endpoint' => $config['endpoint'],
            'signature_version' => 'v4',
        ];

        $s3 = new S3Client($credentials);

        try {
            $s3->headBucket(['Bucket' => $config['bucket']]);
        } catch (Aws\S3\Exception\S3Exception $e) {
            $errors['err'] = sprintf(
                'User does not have access to this bucket: %s', (string)$e
            );
            return false;
        }
        return true;
    }
}