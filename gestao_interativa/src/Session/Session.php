<?php

namespace GestaoInterativa\Session;

class Session
{
    private $started = false;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
        }
    }

    public function start()
    {
        if (!$this->started) {
            session_start();
            $this->started = true;
        }
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function get($key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public function clear()
    {
        $_SESSION = [];
    }

    public function destroy()
    {
        if ($this->started) {
            session_destroy();
            $this->started = false;
        }
    }

    public function regenerate($destroy = false)
    {
        if ($destroy) {
            session_regenerate_id(true);
        } else {
            session_regenerate_id();
        }
    }

    public function flash($key, $value)
    {
        $this->set("flash_{$key}", $value);
    }

    public function getFlash($key)
    {
        $value = $this->get("flash_{$key}");
        $this->remove("flash_{$key}");
        return $value;
    }

    public function hasFlash($key)
    {
        return $this->has("flash_{$key}");
    }

    public function keepFlash($key)
    {
        if ($this->hasFlash($key)) {
            $value = $this->getFlash($key);
            $this->flash($key, $value);
        }
    }

    public function clearFlash()
    {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'flash_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }

    public function all()
    {
        return $_SESSION;
    }

    public function replace(array $data)
    {
        $_SESSION = array_replace($_SESSION, $data);
    }

    public function isStarted()
    {
        return $this->started;
    }

    public function getId()
    {
        return session_id();
    }

    public function setId($id)
    {
        session_id($id);
    }

    public function getName()
    {
        return session_name();
    }

    public function setName($name)
    {
        session_name($name);
    }

    public function getCookieParams()
    {
        return session_get_cookie_params();
    }

    public function setCookieParams(array $params)
    {
        session_set_cookie_params($params);
    }

    public function getSavePath()
    {
        return session_save_path();
    }

    public function setSavePath($path)
    {
        session_save_path($path);
    }

    public function getStatus()
    {
        return session_status();
    }

    public function isActive()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function isExpired()
    {
        if (!$this->isActive()) {
            return false;
        }

        $lastActivity = $this->get('_last_activity');
        $lifetime = $this->getLifetime();

        return $lastActivity && (time() - $lastActivity) > $lifetime;
    }

    public function updateLastActivity()
    {
        $this->set('_last_activity', time());
    }

    public function getLastActivity()
    {
        return $this->get('_last_activity');
    }

    public function setLifetime($lifetime)
    {
        ini_set('session.gc_maxlifetime', $lifetime);
    }

    public function getLifetime()
    {
        return ini_get('session.gc_maxlifetime');
    }

    public function setGcProbability($probability, $divisor = 100)
    {
        ini_set('session.gc_probability', $probability);
        ini_set('session.gc_divisor', $divisor);
    }

    public function getGcProbability()
    {
        return ini_get('session.gc_probability');
    }

    public function setGcDivisor($divisor)
    {
        ini_set('session.gc_divisor', $divisor);
    }

    public function getGcDivisor()
    {
        return ini_get('session.gc_divisor');
    }

    public function setGcMaxLifetime($lifetime)
    {
        ini_set('session.gc_maxlifetime', $lifetime);
    }

    public function getGcMaxLifetime()
    {
        return ini_get('session.gc_maxlifetime');
    }
} 