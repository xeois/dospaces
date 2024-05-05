<?php

return array(
    'id' =>             'storage:digitalocean-spaces',
    'version' =>        '1.0',
    'ost_version' =>    '1.18', # Require osTicket v1.18+
    'name' =>           'Attachments hosted in DigitalOcean Spaces',
    'author' =>         'Abhishek Badar',
    'description' =>    'Enables storing attachments in DigitalOcean Spaces using the S3 API',
    'url' =>            'https://xeois.com', 
    'requires' => array(
        "aws/aws-sdk-php" => array(
            'version' => "3.*",
            'map' => array(
                'aws/aws-sdk-php/src' => 'lib/Aws',
                'guzzlehttp/guzzle/src' => 'lib/GuzzleHttp',
                'guzzlehttp/promises/src' => 'lib/GuzzleHttp/Promise',
                'guzzlehttp/psr7/src/' => 'lib/GuzzleHttp/Psr7',
                'mtdowling/jmespath.php/src' => 'lib/JmesPath',
                'psr/http-client/src' => 'lib/Psr/Http/Client',
                'psr/http-factory/src' => 'lib/Psr/Http/Factory',
                'psr/http-message/src' => 'lib/Psr/Http/Message',
            ),
        ),
    ),
    'scripts' =>  array(
        'pre-autoload-dump' => 'Aws\\Script\\Composer\\Composer::removeUnusedServices',
    ),
    'extra' => array(
        'aws/aws-sdk-php' => ['S3'],  // Only load the S3 module
    ),
    'plugin' =>         'storage.php:DigitalOceanSpacesStoragePlugin'
);

?>