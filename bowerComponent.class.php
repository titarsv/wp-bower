<?php

/**
 * Created by PhpStorm.
 * User: Tit@r
 * Date: 19.03.2016
 * Time: 9:38
 */


/**
 * Class bowerComponent
 */
class bowerComponent
{
    protected $param_keys = array('name', 'description', 'main', 'path', 'url', 'version', 'dependencies', 'dirs', 'files', 'homepage');
    protected $json_keys = array('name', 'description', 'main', 'version', 'dependencies', 'homepage');

    protected $name = '';
    protected $path = '';
    protected $url = '';
    protected $version = '';
    protected $description = '';
    protected $homepage = '';
    protected $bowerComponentPath = '';
    protected $main = array();
    protected $dependencies = array();
    protected $dirs = array();
    protected $files = array();
    protected $dirMap = array();

    /**
     * bowerComponent constructor.
     * @param string $path - Путь к модулю Bower
     * @param string|array $params - Параметры модуля
     * @throws Exception
     */
    public function __construct($path, $params = ''){

        $path = str_replace('\\', '/', $path);

        if(is_dir($path))
            $this->path = $path;
        else
            throw new Exception('Wrong a way!');

        if(is_array($params)){

            $this->setParams($params);

        }elseif(!empty($params)){

            $params_array = maybe_unserialize(str_replace(array('&quot;', '&gt;', '&lt;'), array('"', '>', '<'), $params));
           // print_r(unserialize(str_replace(array('&quot;', '&gt;', '&lt;'), array('"', '>', '<'), $params)));

            if(is_array($params_array))
                $this->setParams($params_array);
            else {
              $this->setParamsFromJson();
            }

        }else{

            $this->setParamsFromJson();

        }

        if(count($this->dirs) == 0)
            $this->setBowerComponentsDirs();

        if(count($this->files) == 0)
            $this->setBowerComponentFiles();

        if(!is_array($this->main) || !is_array($this->main[0]))
            $this->main = $this->setMainParam($this->main);

    }

    /**
     * @param array $params - Параметры модуля
     */
    public function setParams($params){

        foreach($this->param_keys as $key){

            if(isset($params[$key])){

                $this->setParam($key, $params[$key]);

            }elseif(in_array($key, $this->json_keys)){

                if(!isset($json_params))
                    $json_params = $this->getBowerJsonParams();

                if(isset($json_params[$key])){

                    $this->$key = $json_params[$key];

                }

            }

        }

    }

    /**
     * Установка параметров модуля из json файла
     */
    public function setParamsFromJson(){

        $json_params = $this->getBowerJsonParams();

        foreach($this->json_keys as $key){

            if(isset($json_params[$key])){

                $this->$key = $json_params[$key];

            }

        }
    }


    /**
     * Установка параметров
     * @param string $key
     * @param string|array|mixed $value
     */
    private function setParam($key, $value){

        if(method_exists($this, 'set'.ucfirst($key).'Param')){
            $method = 'set'.ucfirst($key).'Param';
            $this->$key = $this->$method($value);
        }else
            $this->$key = $value;

    }


    /**
     * Установка основных файлов модуля
     * @param string|array $main
     * @return array
     */
    public function setMainParam($main){

        if(!is_array($main)){
            $main = array($main);
        }

        if(is_array($main[0]))
            return $main;

        foreach($main as $key => $main_file){
            if(preg_match('/\*$/', $main_file)){
              unset($main[$key]);
              continue;
            }

            $main_file = preg_replace('/^\.?\/?/', '', $main_file);
            $extension = $this->getExtension($main_file);

            if(in_array($extension, array('less', 'sass', 'scss'))){
                $main_file_css = preg_replace('/'.$extension.'$/', 'css', $main_file);
                $name_css = $this->getFileName($main_file_css);
                $files = $this->searchFile($name_css);

                if(count($files) > 0) {
                    $name = $name_css;
                    $extension = 'css';
                }

                if(count($files) == 1)
                    $main_file = $files[0];
                elseif(count($files) > 1)
                    $main_file = $this->getForegroundFile($files, $main);

            }

            if(!isset($name))
                $name = $this->getFileName($main_file);

            $result = array('extension' => $extension);

            if($this->isMinFile($name, $extension)){
                $result['name'] = preg_replace('/.min.'.$extension.'$/', '.'.$extension, $name);
                $result['min'] = $main_file;
                $result['full'] = $this->getFullFile($name, $result['name'], $result['min'], $main);
            }else{
                $result['name'] = $name;
                $result['full'] = $main_file;
                $result['min'] = $this->getMinFile($name, $result['full'], $extension, $main);
            }

            $main[$key] = $result;

            unset($name);
        }

        // Убираем дубли
        $result = $main;
        $main = array();
        foreach($result as $params){
            if(!in_array($params, $main))
                $main[] = $params;
        }

        return $main;
    }

  /**
   * Установка зависимостей
   * @param array $dependencies
   * @return array
   */
    public function setDependenciesParam($dependencies){

      if(is_array(current($dependencies)))
        return $dependencies;

      foreach($dependencies as $key => $dependence){
        if(is_string($dependence)) {
          $compare = '>=';
          $version = '';
          foreach (array('>=', '<=', '=', '>', '<') as $compare) {
            if (strpos(trim($dependence), $compare) === 0) {
              $version = trim(str_replace($compare, '', $dependence));
              break;
            }
          }

          if(empty($version)){
            $compare = '';
            $version = $dependence;
          }

          $dependencies[$key] = array('version' => $version, 'compare' => $compare);
        }

      }

      return $dependencies;
    }

    /**
     * Установка версии
     * @param $version
     * @return mixed
     */
    public function setVersionParam($version){
        return preg_replace('/[^0-9\.]+/', '', $version);
    }

    /**
     * Проверка на минификацию
     * @param string $name
     * @param string $extension
     * @return bool
     */
    public function isMinFile($name, $extension = ''){

        if(empty($extension))
            $extension = $this->getExtension($name);

        if(strrpos($name, '.min.'.$extension, -1) === strlen($name) - strlen('.min.'.$extension))
            return true;
        else
            return false;
    }

    /**
     * Поиск не минифицированной версии файла
     * @param string $name
     * @param string $full_name
     * @param string $min
     * @param array $main
     * @return string
     */
    private function getFullFile($name, $full_name, $min, $main){

        $maybe_full = preg_replace('/'.$name.'$/', '', $min).$full_name;

        if(is_file($this->path.'/'.$maybe_full)){
            return $maybe_full;
        }

        $files = $this->searchFile($full_name);

        if(count($files) == 1)
            return $files[0];
        elseif(count($files) > 1)
            return $this->getForegroundFile($files, $main);

        return '';
    }

    /**
     * Поиск минифицированной версии файла
     * @param string $name
     * @param string $full
     * @param string $extension
     * @param array $main
     * @return string
     */
    private function getMinFile($name, $full, $extension, $main){

        $maybe_min = preg_replace('/.'.$extension.'$/', '.min.'.$extension, $full);

        if(is_file($this->path.'/'.$maybe_min)){
            return $maybe_min;
        }

        $files = $this->searchFile(preg_replace('/.'.$extension.'$/', '.min.'.$extension, $name));

        if(count($files) == 1)
            return $files[0];
        elseif(count($files) > 1)
            return $this->getForegroundFile($files, $main);

        return '';
    }


    /**
     * Поиск файла по имени
     * @param string $name
     * @return array
     */
    private function searchFile($name){

        if(count($this->files) == 0)
            $this->setBowerComponentFiles();

        $files = array();
        foreach($this->files as $path => $file){
            if($file['name'] == $name)
                $files[] = $path;
        }

        return $files;
    }


    /**
     * Поиск наиболее приоритетного файла из набора
     * @param $files
     * @param $main
     * @return string
     */
    private function getForegroundFile($files, $main){

        foreach($files as $file){
            if(in_array($file, $main))
                return $file;
        }

        $dirs = $this->getBowerDirsParams();

        $files_priority = array();
        foreach ($files as $key => $file) {
            $parent_dir = $this->getParentDir($file);
            if(isset($dirs[$parent_dir])){
                $files_priority[$file] = $dirs[$parent_dir];
            }
        }
        asort($files_priority);

        return key($files_priority);
    }

    /**
     * Выделение родительской директории из пути
     * @param string $object
     * @return string
     */
    private function getParentDir($object){
        $parent_dir = preg_replace('/\/'.$this->getFileName($object).'/', '', $object);
        return $parent_dir;
    }

    /**
     * Получение имени файла из пути
     * @param string $file
     * @return string
     */
    private function getFileName($file){
        $file_parts = explode("/", $file);
        $name = end($file_parts);
        return $name;
    }

    /**
     * Получение параметров модуля из json файла
     * @return array|mixed|object
     */
    public function getBowerJsonParams(){
        $bower_json = $this->path . '/.bower.json';
        if(!is_file($bower_json))
            $bower_json = $this->path . '/bower.json';

        if (is_file($bower_json)) {
            $bower = file_get_contents($bower_json);
            $params = json_decode($bower, true);
        }else{
            $params = array();
        }

        return $params;
    }

    /**
     * Получение текущих настроек модуля Bower
     * @return array
     */
    public function getParams(){

        $params = array();

        foreach($this->param_keys as $key){

            if((is_array($this->$key) && count($this->$key)) > 0 || (is_string($this->$key) && !empty($this->$key))){
                $params[$key] = $this->$key;
            }

        }

        return $params;
    }

    /**
     * Установка родительской директории модулей Bower
     * @param string $path
     */
    public function setBowerComponentsPath($path){

        if(is_string($path) && is_dir($path))
            $this->bowerComponentPath = $path;

    }

    /**
     * Создание списка js и css файлов модуля
     */
    private function setBowerComponentFiles(){

        if(count($this->dirMap) == 0)
            $this->dirMap = $this->scanBowerComponentDirs($this->path);

        $this->files = $this->getFilesArray($this->dirMap, $this->path);
    }

    /**
     * Формирование списка файлов
     * @param array $files_in_dir
     * @param string $path
     * @return array
     */
    private function getFilesArray($files_in_dir, $path){
        $files = array();
        if(is_array($files_in_dir)){
            foreach ($files_in_dir as $key => $file) {
                if (is_array($file)) {
                    $files = array_merge($files, $this->getFilesArray($file, $path . '/' . $key));
                } else {
                    $files[preg_replace('/^'.str_replace('/', '\/', $this->path.'/').'/', '', $path . '/' . $file)] = array('name' => $file);
                }
            }
        }
        return $files;
    }

    /**
     * Создание списка директорий модуля
     */
    private function setBowerComponentsDirs(){

        if(count($this->dirMap) == 0)
            $this->dirMap = $this->scanBowerComponentDirs($this->path);

        $this->dirs = $this->getDirsArray($this->dirMap, $this->path);
    }

    /**
     * Формирование списка директорий
     * @param array $files_in_dir
     * @param string $path
     * @return array
     */
    private function getDirsArray($files_in_dir, $path){
        $dirs = array();
        if(is_array($files_in_dir)){
            foreach ($files_in_dir as $key => $dir) {
                if (is_array($dir)) {
                    $dirs[str_replace($this->path, '', $path . '/' . $key)] = array('name' => $key);
                    $dirs = array_merge($dirs, $this->getDirsArray($dir, $path . '/' . $key));
                }
            }
        }
        return $dirs;
    }

    /**
     * Поиск основных директорий модуля
     * @return array
     */
    private function getBowerDirsParams(){
        $main_dirs = array(
            'dist' => array('type' => 'dist', 'priority' => 1, 'dirs' => array()),
            'build' => array('type' => 'build', 'priority' => 2, 'dirs' => array()),
            'builds' => array('type' => 'build', 'priority' => 2, 'dirs' => array()),
            'src' => array('type' => 'src', 'priority' => 3, 'dirs' => array()),
            'min' => array('type' => 'min', 'priority' => 4, 'dirs' => array()),
            'css' => array('type' => 'css', 'priority' => 4, 'dirs' => array()),
            'js' => array('type' => 'js', 'priority' => 4, 'dirs' => array())
        );

        if(count($this->dirs) == 0)
            $this->setBowerComponentsDirs();

        if(count($this->dirs) > 0){
            foreach($this->dirs as $path => $dir){
                if(isset($main_dirs[$dir['name']])){
                    $main_dirs[$dir['name']]['dirs'][] = preg_replace('/^\//', '', $path);
                }
            }
        }

        $result_dirs = array();

        foreach($main_dirs as $key => $val){
            if(count($val['dirs']) > 0) {
                if(isset($result_dirs[$main_dirs[$key]['priority']]))
                    $result_dirs[$main_dirs[$key]['priority']] = array_merge ($result_dirs[$main_dirs[$key]['priority']], $main_dirs[$key]['dirs']);
                else
                    $result_dirs[$main_dirs[$key]['priority']] = $main_dirs[$key]['dirs'];
            }
        }

        return $this->sortDirsByPriority($result_dirs);
    }

    /**
     * Проверка принадлежности объекта директории
     * @param $object
     * @param $dir
     * @return bool
     */
    private function inThisDir($object, $dir){
        if(strpos($object, $dir) === 0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Сортировка директорий по приоритету
     * @param $object
     * @return array
     */
    private function sortDirsByPriority($object){

        $dirs_start_priority = array();
        foreach($object as $priority => $dirs){
            $i = 0;
            foreach($dirs as $dir) {
                $dirs_start_priority[$dir] = $priority + $i;
                $i++;
            }
        }

        $result_dirs_priority = array();
        foreach($dirs_start_priority as $parent_dir => $parent_priority){
            foreach($dirs_start_priority as $dir => $priority){
                if($dir != $parent_dir && $this->inThisDir($dir, $parent_dir)){
                    $result_dirs_priority[$dir] = $parent_priority + $priority/10;
                }elseif(!isset($result_dirs_priority[$dir])){
                    $result_dirs_priority[$dir] = $priority;
                }
            }
        }
        asort($result_dirs_priority);

        return $result_dirs_priority;
    }

    /**
     * Поиск js и css файлов в директории модуля
     * @param string $start - директория
     * @return array - массив влоденных файлов/директорий
     */
    private function scanBowerComponentDirs($start)
    {
        $files = array();
        $handle = opendir($start);
        while (false !== ($file = readdir($handle))){
            if ($file != '.' && $file != '..'){
                if (is_dir($start.'/'.$file)){
                    $dir = $this->scanBowerComponentDirs($start.'/'.$file);
                    $files[$file] = $dir;
                }else{
                    if(in_array($this->getExtension($file), array('js', 'css')))
                        array_push($files, $file);
                }
            }
        }
        closedir($handle);
        return $files;
    }

    /**
     * Получение расширения файла
     * @param string $filename - Имя файла
     * @return array - Расширение
     */
    public function getExtension($filename) {
        $result = explode(".", $filename);
        return end($result);
    }

    /**
     * Получить основные файлы
     * @return array
     */
    public function getMainFiles(){
        return $this->main;
    }

    /**
     * Получить второстепенные файлы
     * @return array
     */
    public function getSecondaryFiles(){
        $all_files = $this->files;

        foreach($this->main as $file){
            if(isset($file['full']))
                unset($all_files[$file['full']]);
            if(isset($file['min']))
                unset($all_files[$file['min']]);
        }

        return $all_files;
    }

    /**
     * Получить номер версии
     * @return string
     */
    public function getVersion(){
        return $this->version;
    }

    /**
     * Включение файла
     * @param string $file
     * @param bool|false $enable
     * @param string $site_part
     * @param bool|false $in_footer
     */
    public function enableDisableFile($file, $enable = false, $site_part = 'frontend', $in_footer = false){
        $this->files[$file]['enabled'] = $enable;
        $this->files[$file]['site_part'] = $site_part;
        $this->files[$file]['in_footer'] = $in_footer;

        foreach($this->main as $key => $main){
            if($enable){
                if($main['full'] == $file) {
                    $this->main[$key]['enabled'] = 'full';
                    $this->main[$key]['site_part'] = $site_part;
                    $this->main[$key]['in_footer'] = $in_footer;
                }elseif($main['min'] == $file) {
                    $this->main[$key]['enabled'] = 'min';
                    $this->main[$key]['site_part'] = $site_part;
                    $this->main[$key]['in_footer'] = $in_footer;
                }
            }else{
                $this->main[$key]['enabled'] = false;
            }
        }
    }

    /**
     * Отключение всех файлов
     */
    public function disableAllFiles(){
        foreach($this->files as $path => $key){
            $this->enableDisableFile($path, false);
        }
    }

  /**
   * Получить зависимости
   * @return array
   */
    public function getDependencies(){
        return $this->dependencies;
    }

  /**
   * Получить активированные файлы
   * @return array
   */
    public function getEnabledFiles(){
      $version = $this->getVersion();
      $dependencies = array();
      foreach($this->getDependencies() as $dependence => $params){
        $dependencies[] = $dependence;
      }
      $enabled_files = array();
      $files_count = array('js' => 0, 'css' => 0, 'js_main' => 0, 'css_main' => 0);

      foreach($this->main as $key => $main){
        if(in_array($main['enabled'], array('full', 'min'))){
          $file = $main[$main['enabled']];
          unset($main['enabled']);
          unset($main['full']);
          unset($main['min']);
          $main['version'] = $version;
          $main['dependencies'] = $dependencies;
          $main['main'] = true;
          $files_count[$main['extension']]++;
          $files_count[$main['extension'].'_main']++;

          $enabled_files[$this->name.'/'.$file] = $main;
        }
      }

      foreach($this->files as $key => $file){
        if($file['enabled'] == true && !isset($enabled_files[$key])){
          unset($file['enabled']);
          $file['extension'] = (string) $this->getExtension($file['name']);
          $file['version'] = $version;
          $file['dependencies'] = $dependencies;
          $files_count[$file['extension']]++;

          $enabled_files[$this->name.'/'.$key] = $file;
        }
      }

      $enabled_files = $this->setEnabledFilesNames($enabled_files, $files_count);

      return $enabled_files;
    }

    public function setEnabledFilesNames($enabled_files, $files_count){
      foreach($enabled_files as $key => $params){
        if($files_count[$params['extension']] == 1 || ($files_count[$params['extension'].'_main'] == 1 && isset($params['main'])))
          $enabled_files[$key]['name'] = $this->name;
        else{
          $enabled_files[$key]['name'] = preg_replace('/(\.min)?.'.$params['extension'].'/', '', $enabled_files[$key]['name']);
        }
        $enabled_files[$key]['module'] = $this->name;

        if(isset($params['main']))
          unset($enabled_files[$key]['main']);
      }

      return $enabled_files;
    }

    public function getRegistrationFiles($min = true){
      $version = $this->getVersion();
      $dependencies = array();
      foreach($this->getDependencies() as $dependence => $params){
        $dependencies[] = $dependence;
      }
      $enabled_files = array();
      $files_count = array('js' => 0, 'css' => 0, 'js_main' => 0, 'css_main' => 0);

      foreach($this->main as $key => $main){
          $file = $min?$main['min']:$main['full'];
          if(isset($main['enabled']))
            unset($main['enabled']);
          unset($main['full']);
          unset($main['min']);
          $main['version'] = $version;
          $main['dependencies'] = $dependencies;
          $main['main'] = true;
          $files_count[$main['extension']]++;
          $files_count[$main['extension'].'_main']++;

          $enabled_files[$this->name.'/'.$file] = $main;
      }

      $enabled_files = $this->setEnabledFilesNames($enabled_files, $files_count);

      return $enabled_files;
    }
}