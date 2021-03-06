<?php


namespace PHPMVC\LIB;


class SessionManager extends \SessionHandler
{

    private $sessionName = SESSION_NAME;
    private $sessionMaxLifeTime = SESSION_LIFE_TIME;
    private $sessionSSL = false;
    private $sessionHTTPOnly = True;
    private $sessionPath = '/';
    private $sessionDomain = '.acc-system.com';
    private $sessionSavePath = SESSION_SAVE_PATH;

    private $sessionCipherAlgo = 'AES-128-ECB';
//    private $sessionCipherMode = MCRYPT_MODE_ECB;
    private $sessionCipherKey = "WCRYPT0K3Y@2019";

    private $ttl = 30;

    public function __construct()
    {
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);  // prevent any thing except cookies
        ini_set('session.use_trans_sid', 0);     // prevent access via url
        ini_set('session.save_handler', 'files');

        session_name($this->sessionName);
        session_save_path($this->sessionSavePath);
        session_set_cookie_params(
            $this->sessionMaxLifeTime, $this->sessionPath, $this->sessionDomain,
            $this->sessionSSL, $this->sessionHTTPOnly);
//        session_set_save_handler($this,true);

    }

    public function read($id)
    {
        return openssl_decrypt(parent::read($id), $this->sessionCipherAlgo, $this->sessionCipherKey);

    }

    public function write($id, $data)
    {
        return parent::write($id, openssl_encrypt($data, $this->sessionCipherAlgo, $this->sessionCipherKey));
    }

    public function __get($key)
    {
        if (isset($_SESSION[$key])) {
            $data = @unserialize($_SESSION[$key]);
            if ($data === false) {
                return $_SESSION[$key];
            } else {
                return $data;
            }
        } else {
            trigger_error("No Session key " . $key . ' exists', E_USER_NOTICE);
        }
    }

    public function __set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function __isset($key)
    {
        return isset($_SESSION[$key]) ? true : false;
    }


    public function start()
    {
        if ('' === session_id()) {
            if (session_start()) {
                $this->setSessionStartTime();
                $this->checkSessionValidity();

            }
        }
    }

    private function setSessionStartTime()
    {
        if (!isset($this->sessionStartTime)) {
            $this->sessionStartTime = time();
        }
    }

    private function checkSessionValidity()
    {
        if ((time() - $this->sessionStartTime) > ($this->ttl * 60)) {
            $this->renewSession();
            $this->generateFingerPrint();
        }
        return true;

    }

    private function renewSession()
    {
        $this->sessionStartTime = time();
        session_regenerate_id(true);
    }

    public function kill()
    {
        session_unset();
        setcookie(
            $this->sessionName, '', time() - 1000,
            $this->sessionPath, $this->sessionDomain,
            $this->sessionSSL, $this->sessionHTTPOnly);
        session_destroy();
    }

    private function generateFingerPrint()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $this->cipherKey = openssl_random_pseudo_bytes(16);//mcrypt_create_iv(32);
        $sessionId = session_id();
        $this->fingerPrint = md5($userAgent . $this->cipherKey . $sessionId);

    }

    public function isValidFingerPrint()
    {
        if (!isset($this->fingerPrint)) {
            $this->generateFingerPrint();
        }
        $fingerPrint = md5($_SERVER['HTTP_USER_AGENT'] . $this->cipherKey . sessionId());
        if ($fingerPrint === $this->fingerPrint) {
            return true;
        }
        return false;
    }
}
