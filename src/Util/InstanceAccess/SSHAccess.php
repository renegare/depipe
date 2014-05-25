<?php

namespace App\Util\InstanceAccess;

use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;
use App\Platform\InstanceAccessInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class SSHAccess implements InstanceAccessInterface {
    use LoggerTrait, LoggerAwareTrait;

    protected $credentials;
    protected $conn;
    protected $host;

    /**
     * {@inheritdoc}
     */
    public function setCredentials(array $credentials) {
        $this->credentials = new ParameterBag($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function connect($instanceHost) {
        $attempts = 0;
        $maxAttempts = $this->get('connect.attempts', 1);
        $sleepSeconds = $this->get('connect.sleep');
        while($attempts < $maxAttempts) {
            ++$attempts;
            try {
                $this->info(sprintf('SSH connecting (attemtp %s)...', $attempts));
                $conn = new \Net_SSH2($instanceHost, 22);
                $conn->login($this->get('user'), $this->getPassword());
                $this->conn = $conn;
                $this->host = $instanceHost;
                $this->info('SSH connected :)');
                return true;
            } catch (\Exception $e) {
                $this->warning('SSH connection error: ' . $e->getMessage());
                $this->info(sprintf('Will try again in %s seconds ...', $sleepSeconds));
                sleep($sleepSeconds);
            }
        }
        $this->error('SSH connection failed ... giving up! :(');
        if(isset($e)) {
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exec($code, \Closure $callback = null) {
        if(!$this->conn) {
            throw new \Exception('You are not connected to the server!');
        }

        $this->info('Connecting to the instance via SFTP ...');
        $sftp = new \Net_SFTP($this->host, 22);
        $sftp->login($this->get('user'), $this->getPassword());

        $this->info('Executing code ...');
        $sftp->put('/tmp/execute.sh', $code);
        $sftp->chmod(0550, '/tmp/execute.sh');
        $this->conn->exec('/tmp/execute.sh', function($output) {
            $this->info(sprintf('@%s %s', $output));
        });

        $exitCode = $this->conn->getExitStatus();
    }

    /**
     * proxy for ParameterBag::get
     * {@inheritdoc ParameterBag::get}
     */
    public function get($path, $default = null, $deep = false) {
        return $this->credentials->get($path, $default, $deep);
    }

    public function getPassword() {
        if(!($password = $this->get('password'))) {
            $key = $this->get('key');
            $password = new \Crypt_RSA();
            $password->loadKey($key);
        }

        return $password;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array()) {
        if($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
