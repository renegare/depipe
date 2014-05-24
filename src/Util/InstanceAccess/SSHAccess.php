<?php

namespace App\Util\InstanceAccess;

use App\Platform\InstanceAccessInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class SSHAccess implements InstanceAccessInterface {

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
        while($attempts < $maxAttempts) {
            try {
                $conn = new \Net_SSH2($instanceHost, 22, 1);
                $conn->login($this->get('user'), $this->get('password'));
                $this->conn = $conn;
                $this->host = $instanceHost;
                return true;
            } catch (\Exception $e) {
                ++$attempts;
            }
        }

        throw $e;
    }

    /**
     * {@inheritdoc}
     */
    public function exec($code, $cb) {
        if(!$this->conn) {
            throw new \Exception('You are not connected to the server');
        }

        $sftp = new \Net_SFTP($this->host, 22, 1);
        $sftp->login($this->get('user'), $this->get('password'));
        $sftp->put('/tmp/execute.sh', $code);
        $sftp->chmod(0550, '/tmp/execute.sh');
        $this->conn->exec('/tmp/execute.sh', $cb);

        $exitCode = $this->conn->getExitStatus();
    }

    /**
     * proxy for ParameterBag::get
     * {@inheritdoc ParameterBag::get}
     */
    public function get($path, $default = null, $deep = false) {
        return $this->credentials->get($path, $default, $deep);
    }
}
