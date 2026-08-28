<?php
class PM_Manager
{
    private static $name;

    public static function init($name){self::$name=$name;}

    private static function t($key,$default){
        global $L;
        if(isset($L)){
            $v=$L->get($key);
            if(is_string($v)&&$v!==""&&$v!==$key)return $v;
        }
        return $default;
    }

    public static function run(){
        if(!isset($_REQUEST["pm_action"])||!is_string($_REQUEST["pm_action"]))return;
        
        switch($_REQUEST["pm_action"]){
            case "delete":self::delete();break;
            case "upload":self::upload();break;
        }
    }

    public static function form(){
        global $security;
        
        $token=htmlspecialchars($security->getTokenCSRF(),ENT_QUOTES,"UTF-8");
        return '<input type="hidden" name="pm_action" value="upload"><input type="hidden" name="tokenCSRF" value="'.$token.'"><div class="card-body card metric-card" style="display:block;"><p>'.htmlspecialchars(self::t("upload-help","To install a new plugin, choose the plugin ZIP file and press the upload button."),ENT_QUOTES,"UTF-8").'</p><p><input type="file" name="plugin_zip" accept=".zip" style="unicode-bidi:isolate" required></p><button class="btn btn-primary" type="submit">'.htmlspecialchars(self::t("upload-plugin","Upload Plugin"),ENT_QUOTES,"UTF-8").'</button></div><script>(function(){var f=document.querySelector("input[name=plugin_zip]");if(!f||!f.form)return;f.form.method="post";f.form.enctype="multipart/form-data";f.form.encoding="multipart/form-data";})();</script>';
    }

    public static function pluginsPage(){
        if(!isset($_SERVER["REQUEST_URI"]))return false;
        
        $path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);
        return rtrim($path,"/")===rtrim(HTML_PATH_ADMIN_ROOT,"/")."/plugins";
    }

    public static function pluginsPageUI(){
    	global $security, $plugins;

    	$managerUrl=HTML_PATH_ADMIN_ROOT."configure-plugin/pluginPluginManager";
    	$token=rawurlencode($security->getTokenCSRF());
    	$map=[];
	if(isset($plugins['all'])&&is_array($plugins['all'])){
        foreach($plugins['all'] as $plugin){
            if(!is_object($plugin))continue;
            if(!isset($plugin->className)||!isset($plugin->directoryName))continue;

            $map[$plugin->className]=$plugin->directoryName;
        }
    }
    $uploadText=self::t("upload-plugin","Upload Plugin");
    $deleteText=self::t("delete","Delete");
    $deleteConfirm=self::t("delete-confirm","Delete this plugin permanently?");
        
        echo '<style>.pm-delete{margin-inline-start:12px;color:#dc3545!important;cursor:pointer}.pm-delete:hover{text-decoration:underline}.pm-upload-button{margin-left:15px;margin-right:15px}</style>';
        echo '<script>(function(){var pluginMap='.json_encode($map).',managerUrl='.json_encode($managerUrl).',csrfToken='.json_encode($token).';function pmInit(){var managerLinks=document.querySelectorAll(\'a[href*="/configure-plugin/"]\');for(var i=0;i<managerLinks.length;i++){var ml=managerLinks[i],mhref=ml.getAttribute("href")||"";if(mhref.indexOf('.json_encode("/configure-plugin/".self::$name).')!==-1){var row=ml.closest("tr");if(row)row.style.display="none";break}}var positions=null,allLinks=document.querySelectorAll("a[href]");for(var j=0;j<allLinks.length;j++){var ph=allLinks[j].getAttribute("href")||"";if(ph.replace(/\/+$/,"").match(/\/plugins-position$/)){positions=allLinks[j];break}}if(positions&&!document.querySelector(".pm-upload-button")){var upload=document.createElement("a");upload.className="pm-upload-button";upload.href=managerUrl;upload.innerHTML='.json_encode('<span class="fa fa-upload"></span> '.$uploadText).';positions.parentNode.insertBefore(upload,positions.nextSibling)}var activateLinks=document.querySelectorAll(\'a[href*="/install-plugin/"]\');for(var k=0;k<activateLinks.length;k++){var activate=activateLinks[k];if(activate.getAttribute("data-pm"))continue;var ahref=activate.getAttribute("href")||"",m=ahref.match(/\/install-plugin\/([^\/?#]+)/);if(!m)continue;var className=decodeURIComponent(m[1]);if(className==="pluginPluginManager")continue;var realFolder=pluginMap[className];if(!realFolder)continue;var del=document.createElement("a");del.className="pm-delete";del.textContent='.json_encode(" ".$deleteText).';del.href='.json_encode($managerUrl."?pm_action=delete&tokenCSRF=".$token."&plugin=").'+encodeURIComponent(realFolder);del.onclick=function(e){if(!confirm('.json_encode($deleteConfirm).')){e.preventDefault();return false}return true};activate.parentNode.insertBefore(del,activate.nextSibling);activate.setAttribute("data-pm","1")}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",pmInit);else pmInit()})();</script>';
    }

    private static function delete(){
        global $security;
        
        $token=isset($_REQUEST["tokenCSRF"])?$_REQUEST["tokenCSRF"]:"";
        if(!$security->validateTokenCSRF($token))self::back(self::t("invalid-security-token","Invalid security token."));
        
        $name=isset($_REQUEST["plugin"])?$_REQUEST["plugin"]:"";
        if(!is_string($name)||$name===""||!PM_Persistence::valid($name))self::back(self::t("invalid-plugin","Invalid plugin."));
        if($name===self::$name)self::back(self::t("cannot-delete-manager","The Plugin Manager cannot delete itself."));
        
        $plugin=rtrim(PATH_PLUGINS,DS).DS.$name;
        if(!is_dir($plugin))self::back(self::t("plugin-folder-not-found","Plugin folder not found: ").$plugin);
        
        $db=rtrim(PATH_PLUGINS_DATABASES,DS).DS.$name.DS."db.php";
        if(is_file($db))self::back(self::t("deactivate-before-delete","Deactivate the plugin before deleting it."));
        if(!self::remove($plugin))self::back(self::t("could-not-delete-plugin-folder","Could not delete plugin folder."));
        if(is_dir($plugin))self::back(self::t("plugin-folder-still-exists","Plugin folder still exists."));
        
        $dbDir=rtrim(PATH_PLUGINS_DATABASES,DS).DS.$name;
        if(is_dir($dbDir))self::remove($dbDir);
        if(defined("PATH_WORKSPACES")){
            $workspace=rtrim(PATH_WORKSPACES,DS).DS.$name;
            if(is_dir($workspace))self::remove($workspace);
        }
        PM_Persistence::delete($name);
        self::back(self::t("plugin-deleted","Plugin deleted."));
    }

    private static function upload(){
        global $security;
        
        $token=isset($_REQUEST["tokenCSRF"])?$_REQUEST["tokenCSRF"]:"";
        if(!$security->validateTokenCSRF($token))self::back(self::t("invalid-security-token","Invalid security token."));
        if(!isset($_FILES["plugin_zip"]))self::back(self::t("upload-file-missing","Upload file is missing."));
        if($_FILES["plugin_zip"]["error"]!==UPLOAD_ERR_OK)self::back(self::t("upload-failed-error","Upload failed. Error code: ").(int)$_FILES["plugin_zip"]["error"]);
        
        $tmp=$_FILES["plugin_zip"]["tmp_name"];
        if(!is_uploaded_file($tmp))self::back(self::t("invalid-uploaded-file","Invalid uploaded file."));
        if(!class_exists("ZipArchive"))self::back(self::t("ziparchive-unavailable","ZipArchive is not available."));
        
        $zip=new ZipArchive();
        if($zip->open($tmp)!==true)self::back(self::t("invalid-zip","Invalid ZIP file."));
        
        $root=self::zipRoot($zip);
        if(!$root){$zip->close();self::back(self::t("invalid-bludit-plugin-zip","Invalid Bludit plugin ZIP."));}
        if($root===self::$name||!PM_Persistence::valid($root)){$zip->close();self::back(self::t("invalid-plugin","Invalid plugin."));}
        
        $target=rtrim(PATH_PLUGINS,DS).DS.$root.DS;
        if(is_dir($target)){$zip->close();self::back(self::t("plugin-already-exists","Plugin already exists."));}
        if(!self::safeZip($zip,$root)){$zip->close();self::back(self::t("unsafe-zip","Unsafe ZIP file."));}
        if(!@mkdir($target,DIR_PERMISSIONS,true)){$zip->close();self::back(self::t("could-not-create-plugin-directory","Could not create plugin directory."));}
        
        for($i=0;$i<$zip->numFiles;$i++){
            $s=$zip->statIndex($i);
            if(!$s||!isset($s["name"])){$zip->close();self::remove($target);self::back(self::t("invalid-zip","Invalid ZIP file."));}
            
            $name=str_replace("\\","/",ltrim($s["name"],"/"));
            if($name===$root)continue;
            if(substr($name,-1)==="/"){
                $relative=substr($name,strlen($root)+1);
                if($relative==="")continue;
                
                $dir=$target.str_replace("/",DS,$relative);
                if(!is_dir($dir)&&!@mkdir($dir,DIR_PERMISSIONS,true)){$zip->close();self::remove($target);self::back(self::t("could-not-create-plugin-directory","Could not create plugin directory."));}
                continue;
            }
            
            $prefix=$root."/";
            if(strpos($name,$prefix)!==0){$zip->close();self::remove($target);self::back(self::t("unsafe-zip","Unsafe ZIP file."));}
            
            $relative=substr($name,strlen($prefix));
            if($relative==="")continue;
            
            $destination=$target.str_replace("/",DS,$relative);
            $parent=dirname($destination);
            if(!is_dir($parent)&&!@mkdir($parent,DIR_PERMISSIONS,true)){$zip->close();self::remove($target);self::back(self::t("could-not-create-plugin-directory","Could not create plugin directory."));}
            
            $input=$zip->getStream($s["name"]);
            if(!$input){$zip->close();self::remove($target);self::back(self::t("could-not-read-zip","Could not read ZIP file."));}
            
            $output=@fopen($destination,"wb");
            if(!$output){fclose($input);$zip->close();self::remove($target);self::back(self::t("could-not-write-plugin-file","Could not write plugin file."));}
            
            while(!feof($input)){
                $buffer=fread($input,8192);
                if($buffer===false){fclose($input);fclose($output);$zip->close();self::remove($target);self::back(self::t("could-not-extract-plugin","Could not extract plugin."));}
                
                if($buffer!==""&&fwrite($output,$buffer)===false){fclose($input);fclose($output);$zip->close();self::remove($target);self::back(self::t("could-not-write-plugin-file","Could not write plugin file."));}
            }
            fclose($input);
            fclose($output);
        }
        $zip->close();
        if(!is_file($target."plugin.php")||!is_file($target."metadata.json")){self::remove($target);self::back(self::t("invalid-bludit-plugin","Invalid Bludit plugin."));}
        self::back(self::t("plugin-uploaded","Plugin uploaded successfully."));
    }

    private static function zipRoot($zip){
        $root=false;
        
        for($i=0;$i<$zip->numFiles;$i++){
            $s=$zip->statIndex($i);
            if(!$s||empty($s["name"]))continue;
            
            $parts=explode("/",str_replace("\\","/",trim($s["name"],"/")));
            if(count($parts)<2)continue;
            if($root===false)$root=$parts[0];
            
            elseif($root!==$parts[0])return false;
        }
        return $root;
    }

    private static function safeZip($zip,$root){
        for($i=0;$i<$zip->numFiles;$i++){
            $s=$zip->statIndex($i);
            if(!$s||!isset($s["name"]))return false;
            
            $parts=explode("/",str_replace("\\","/",trim($s["name"],"/")));
            if(empty($parts)||$parts[0]!==$root)return false;
            
            foreach($parts as $p)if($p==="."||$p===".."||strpos($p,"\0")!==false)return false;
        }
        return true;
    }

    private static function remove($path){
        if(!file_exists($path))return true;
        if(is_file($path)||is_link($path))return @unlink($path);
        
        $items=@scandir($path);
        if(!is_array($items))return false;
        foreach($items as $item)if($item!=="."&&$item!==".."&&!self::remove($path.DS.$item))return false;
        
        return @rmdir($path);
    }

    private static function back($message){
        Alert::set($message);
        Redirect::page("plugins");
        exit();
    }
}
