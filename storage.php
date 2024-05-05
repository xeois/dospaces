<?php

use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use Aws\S3\Exception\SignatureDoesNotMatchException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
require_once INCLUDE_DIR . 'class.json.php';
require_once 'lib/Aws/functions.php';
require_once 'lib/GuzzleHttp/functions.php';

class DigitalOceanSpacesStorageBackend extends FileStorageBackend {
    static $desc;

    static $config;
    static $__config;
    private $body;
    private $upload_hash;
    private $upload_hash_final;
    static $version = '2006-03-01';
    static $sig_vers = 'v4';

    static $blocksize = 8192; // Default read size for sockets

    static function setConfig($config) {
        static::$config = $config->getInfo();
        static::$__config = $config;
    }
    function getConfig() {
        return static::$__config;
    }

    function __construct($meta) {
        parent::__construct($meta);
        $credentials = array(
            'credentials' => array(
                'key' => static::$config['access-key-id'],
                'secret' => static::$config['secret-access-key']
            ),
            'version' => self::$version,
            'region' => static::$config['region'],
            'endpoint' => static::$config['endpoint'],
            'signature_version' => self::$sig_vers
        );

        $this->client = new S3Client($credentials);
    }

    function read($bytes=false, $offset=0) {
        try {
            if (!$this->body)
                $this->openReadStream();
            $chunk = '';
            $bytes = $bytes ?: self::getBlockSize();
            while (strlen($chunk) < $bytes) {
                $buf = $this->body->read($bytes - strlen($chunk));
                if (!$buf) break;
                $chunk .= $buf;
            }
            return $chunk;
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException(self::getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function passthru() {
        try {
            while ($block = $this->read())
                print $block;
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException(self::getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function write($block) {
        if (!$this->body)
            $this->openWriteStream();
        if (!isset($this->upload_hash))
            $this->upload_hash = hash_init('md5');
        hash_update($this->upload_hash, $block);
        return $this->body->write($block);
    }

    function flush() {
        return $this->upload($this->body);
    }

    function upload($filepath) {
        if ($filepath instanceof Stream) {
            $filepath->rewind();
        } elseif (is_string($filepath)) {
            $this->upload_hash = hash_init('md5');
            hash_update_file($this->upload_hash, $filepath);
            $filepath = fopen($filepath, 'r');
            rewind($filepath);
        }

        try {
            $params = array(
                'ContentType' => $this->meta->getType(),
                'CacheControl' => 'private, max-age=86400',
            );
            if (isset($this->upload_hash))
                $params['Content-MD5'] = $this->upload_hash_final = hash_final($this->upload_hash);

            $info = $this->client->upload(
                static::$config['bucket'],
                self::getKey(true),
                $filepath,
                static::$config['acl'] ?: 'private',
                array('params' => $params)
            );
            return true;
        }
        catch (S3Exception $e) {
            throw new IOException('Unable to upload to DigitalOcean Spaces: '.(string)$e);
        }
        return false;
    }

    protected function getSignedRequest($command, $expires) {
        // `$expires` can be a number of seconds or a DateTime object specifying when the URL should expire
        if (!($expires instanceof \DateTimeInterface) && is_numeric($expires)) {
            $expires = '+' . $expires . ' seconds';  // Ensure the string format for relative time is correct
        }
        
        try {
            return $this->client->createPresignedRequest($command, $expires);
        } catch (\Exception $e) {
            throw new \Exception("Error creating signed request: " . $e->getMessage());
        }
    }
    
    

    function sendRedirectUrl($disposition='inline', $ttl = null) {
        $now = time();
        $ttl = $ttl ?: 3600; // Default to 1 hour if no TTL provided, adjust as needed
    
        $filename = basename($this->meta->getName());
        $filename = addcslashes($filename, '"\\');
    
        $command = $this->client->getCommand('GetObject', [
            'Bucket' => static::$config['bucket'],
            'Key'    => self::getKey(),
            'ResponseContentDisposition' => sprintf('%s; filename="%s"', $disposition, $filename)
        ]);
    
        $request = $this->getSignedRequest($command, $ttl);
        Http::redirect((string) $request->getUri());
        return true;
    }
    
    function unlink() {
        try {
            $this->client->deleteObject(array(
                'Bucket' => static::$config['bucket'],
                'Key'    => self::getKey()
            ));
            return true;
        }
        catch (S3Exception $e) {
            throw new IOException('Unable to remove object: '
                . (string) $e);
        }
    }

    protected function openReadStream() {
        $this->getBody(true);
        return true;
    }

    protected function openWriteStream() {
        $this->body = new Stream(fopen('php://temp', 'r+'));
    }

    protected function getBody($stream=false) {
        $params = array(
            'Bucket' => static::$config['bucket'],
            'Key'    => self::getKey(),
        );
    
        $command = $this->client->getCommand('GetObject', $params);
        $command['@http']['stream'] = $stream;
        $result = $this->client->execute($command);
        $this->body = $result['Body'];
        return $this->body;
    }
    

    function getKey($create=false) {
        $attrs = $create ? self::getAttrs() : $this->meta->getAttrs();
        $attrs = JsonDataParser::parse($attrs);
    
        $key = ($attrs && isset($attrs['folder']) && $attrs['folder']) ?
            sprintf('%s/%s', rtrim($attrs['folder'], '/'), ltrim($this->meta->getKey(), '/')) :
            $this->meta->getKey();
    
        return $key;
    }

    function getAttrs() {
        $bucket = static::$config['bucket'];
        $folder = (static::$config['folder'] ? static::$config['folder'] : '');
        $attr = JsonDataEncoder::encode(array('bucket' => $bucket, 'folder' => $folder));

        return $attr;
    }
}

require_once 'config.php';

class DigitalOceanSpacesStoragePlugin extends Plugin {
    var $config_class = 'DigitalOceanSpacesConfig';

    function isMultiInstance() {
        return false;
    }

    function bootstrap() {
        require_once 'storage.php';

        $bucketPath = sprintf('%s%s', $this->getConfig()->get('bucket'),
            $this->getConfig()->get('folder') ? '/'. $this->getConfig()->get('folder') : '');
        DigitalOceanSpacesStorageBackend::setConfig($this->getConfig());
        DigitalOceanSpacesStorageBackend::$desc = sprintf('DigitalOcean Spaces (%s)', $bucketPath);
        FileStorageBackend::register('3', 'DigitalOceanSpacesStorageBackend');
    }
}

require_once INCLUDE_DIR . 'UniversalClassLoader.php';
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();