<?php

class pluginPluginManager extends Plugin
{
    public function init()
    {
        $db = rtrim(PATH_PLUGINS_DATABASES, DS) . DS . $this->directoryName . DS . "db.php";

        if (!is_file($db)) {
            return;
        }

        $dir = PATH_CONTENT . "databases" . DS . "persistent-plugin-settings" . DS;

        if (!is_dir($dir)) {
            @mkdir($dir, DIR_PERMISSIONS, true);
        }

        if (!is_dir($dir)) {
            return;
        }

        require_once $this->phpPath() . "inc" . DS . "persistence.php";
        require_once $this->phpPath() . "inc" . DS . "manager.php";

        PM_Persistence::init($dir, $this->directoryName);
        PM_Manager::init($this->directoryName);
    }

    public function beforeAdminLoad()
    {
        if (!class_exists("PM_Persistence")) {
            return;
        }

        PM_Manager::run();
        PM_Persistence::run();
    }

    public function form()
    {
        return PM_Manager::form();
    }

    public function adminBodyEnd()
    {
        if (!class_exists("PM_Manager")) {
            return;
        }

        if (PM_Manager::pluginsPage()) {
            PM_Manager::pluginsPageUI();
        }
    }
}
