<?php

namespace App\Util\InstanceAccess;

use Psr\Log\LoggerTrait;
use Psr\Log\LoggerAwareTrait;
use App\Platform\InstanceAccessInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use App\Util\Net\SFTP;
use App\Util\Net\SSH2;

class SSHAccess implements InstanceAccessInterface {
    use LoggerTrait, LoggerAwareTrait;

    protected $credentials;
    protected $conn;
    protected $host;
    protected $sftp;

    public function __construct() {
        $this->credentials = new ParameterBag();
    }

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
                $conn = new SSH2($instanceHost, 22);
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

    public function getSFTPConnection($host, $password, $port = 22) {
        if(!$this->sftp) {

            $this->info('Connecting to the instance via SFTP ...');
            $attempts = 0;
            $maxAttempts = $this->get('connect.attempts', 1);
            $sleepSeconds = $this->get('connect.sleep');

            while($attempts < $maxAttempts) {
                ++$attempts;
                try {
                    $sftp = new SFTP($this->host, 22);
                    if(!($response = $sftp->login($this->get('user'), $this->getPassword()))) {
                        throw new \RuntimeException('Failed SFTP Connection');
                    }
                    $this->sftp = $sftp;
                    break;
                } catch (\Exception $e) {
                    $this->warning('SFTP connection error: ' . $e->getMessage());
                    $this->info(sprintf('Will try again in %s seconds ...', $sleepSeconds));
                    sleep($sleepSeconds);
                }
            }

            if(!$this->sftp) {
                throw new \RuntimeException(sprintf('Failed SFTP Connection after %s attempts', $maxAttempts));
            }
        }

        return $this->sftp;
    }

    /**
     * {@inheritdoc}
     */
    public function exec($code, \Closure $callback = null) {
        if(!$this->conn) {
            throw new \Exception('You are not connected to the server!');
        }

        $sftp = $this->getSFTPConnection($this->host, $this->getPassword(), 22);
        $this->info(sprintf('Executing code `%s ...', substr($code, 0, 100)));
        $sftp->put('/tmp/execute.sh', $code);
        $sftp->chmod(0550, '/tmp/execute.sh');
        $response = $this->conn->exec('/tmp/execute.sh', function($output) {
            $this->info(sprintf("[%s]:\n%s", $this->host, $output));
        });

        $exitCode = $this->conn->getExitStatus();

        if($exitCode !== 0) {
            $this->critical('Erronous code detected', ['message' => $this->conn->getLastError(), 'script' => $code, 'code' => $exitCode]);
            throw new \RuntimeException(sprintf('Script exit code was %s', $exitCode), $exitCode);
        }
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
            $this->info('No ssh password found. Lets try with a private key (if it exits!) ...');
            $key = $this->get('key');
            $password = new \Crypt_RSA();
            $password->loadKey($key);
            $this->credentials->set('password', $password);
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
