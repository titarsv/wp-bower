<?php
  require_once "bowerComponent.class.php";

/**
 * Подключение скриптов и стилей плагина
 */
  function wp_bower_plugin_admin_init() {
    wp_register_script( 'jquery-json', plugins_url( '/js/jquery.json.min.js', __FILE__ ), array( 'jquery' ) );
    wp_enqueue_script( 'jquery-json' );
    wp_register_script( 'jquery-loader', plugins_url( '/js/jquery.loader.min.js', __FILE__ ), array( 'jquery' ) );
    wp_enqueue_script( 'jquery-loader' );
    wp_register_script( 'bower-admin', plugins_url( '/js/bower-admin.js', __FILE__ ), array( 'jquery', 'jquery-json', 'jquery-loader' ) );
    wp_enqueue_script( 'bower-admin' );
    wp_deregister_script('autosave');

    wp_register_style( 'jquery-loader', plugins_url( '/css/jquery.loader.min.css', __FILE__ ) );
    wp_enqueue_style( 'jquery-loader' );
    wp_register_style( 'bower-admin', plugins_url( '/css/bower-admin.css', __FILE__ ) );
    wp_enqueue_style( 'bower-admin' );
  }
  add_action( 'admin_init', 'wp_bower_plugin_admin_init' );

/**
 * Поиск модулей
 */
  add_action('wp_ajax_find_bower_components_action', 'find_bower_components_callback');
  function find_bower_components_callback() {

    if(!empty($_POST['find'])){
      $find = preg_replace('/\s/', '-', $_POST['find']);
      chdir(WP_CONTENT_DIR);
      ob_start();
      passthru('php '.WP_PLUGIN_DIR.'/wp-bower/bowerphp.phar search '.$find);
      $out = ob_get_contents();
      ob_end_clean();

      $return = explode(PHP_EOL, $out);

      echo '<table id="bower-components">';

      foreach ($return as $str) {
        if (in_array($str, array('Search results:', 'No results.')))
          echo '<tr><td colspan="2"><h3>' . $str . '</h3></td></tr>';
        else {
          $params = explode(' ', trim($str));
          if (count($params) == 2)
            echo '<tr><td><button data-bower-component="' . $params[0] . '">'.__('Install', 'wpb').'</button></td><td><b>' . $params[0] . ':</b> <a href="https://' . str_replace(array('git://', '.git'), '', $params[1]) . '" target="_blank">' . $params[1] . '</a>' . '</td></tr>';
        }
      }

      echo '</table>';

    }

    wp_die();
  }

/**
 * Установка модуля
 */
  add_action('wp_ajax_install_bower_component_action', 'install_bower_component_callback');
  function install_bower_component_callback() {

    if(!empty($_POST['component'])){
      chdir(WP_BOWER_INSTALL_DIR);
      ob_start();
      passthru('php '.WP_PLUGIN_DIR.'/wp-bower/bowerphp.phar install '.$_POST['component']);
      $out = ob_get_contents();
      ob_end_clean();

      preg_match_all("/bower\s+(\S+)\s+install/", $out, $matches, PREG_SET_ORDER);

      if(count($matches) > 0){
        echo '<table id="bower-installed-components"><thead><tr><td>'.__('Status', 'wpb').'</td><td>'.__('Component', 'wpb').'</td><td>'.__('Version', 'wpb').'</td></tr></thead><tbody>';

        foreach($matches as $component){
          $params = explode('#', $component[1]);
          if($params[0] != $_POST['component'])
            save_bower_component($params[0], $params[1]);
          if(count($params) == 2)
            echo '<tr class="installed-bower-component" data-name="'.$params[0].'" data-version="'.$params[1].'"><td>Installed</td><td><b>'.$params[0].'</b></td><td>'.$params[1].'</td></tr>';
        }

        echo '</tbody></table>';
      }else{
        echo '<p>'.$out.'</p>';
      }

    }

    wp_die();
  }

/**
 * Сохранение зависимостей
 * @param $name
 * @param $version
 */
  function save_bower_component($name, $version){
    global $wpdb;

    if(empty($wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '$name' LIMIT 0, 1"))){

      $post_data = array(
        'post_title'    => wp_strip_all_tags( $name ),
        'post_content'  => '',
        'post_excerpt'  => wp_strip_all_tags( $version ),
        'post_status'   => 'publish',
        'post_type'     => 'bower_component',
        'post_author'   => get_current_user_id()
      );

      wp_insert_post( $post_data );
    }
  }

  /**
   * Сохранение параметров модуля
   */
  add_filter('wp_insert_post_data', 'set_bower_component_settings', 10, 1);
  function set_bower_component_settings($data){
    if($data['post_type'] == 'bower_component'){

      if(!empty($data['post_title']) && is_dir(WP_BOWER_INSTALL_DIR . '/bower_components/' . $data['post_title'])) {

        $bowerComponent = new bowerComponent(WP_BOWER_INSTALL_DIR . '/bower_components/' . $data['post_title'], $data['post_content']);

        $registerFiles = $bowerComponent->getRegistrationFiles(true);
        registerMainFiles($registerFiles, $data['post_title']);

        $bowerComponent->disableAllFiles();

        if (isset($_POST['file']) && is_array($_POST['file'])) {
          foreach ($_POST['file'] as $file => $name) {
            $bowerComponent->enableDisableFile($_POST['url-' . $file], true, $_POST['site_part-' . $file], $_POST['location-' . $file]);
          }
        }

        $enabledFiles = $bowerComponent->getEnabledFiles();
        activateFilesInWP($enabledFiles, $data['post_title']);

        $bowerComponentParams = $bowerComponent->getParams();
        $data['post_content'] = serialize($bowerComponentParams);

        if (empty($data['post_excerpt'])) {
          $data['post_excerpt'] = $bowerComponent->getVersion();
        }
      }

    }

    return $data;
  }

/**
 * Регистрация основных файлов модуля в Wordpress
 * @param $files
 * @param $name
 */
  function registerMainFiles($files, $name){
    $registeredBowerFiles = get_option('registeredBowerFiles', array());
    if(is_array($registeredBowerFiles)){
      foreach($registeredBowerFiles as $file => $params){
        if($params['module'] == $name)
          unset($registeredBowerFiles[$file]);
      }
    }
    $registeredBowerFiles = array_merge($registeredBowerFiles, $files);
    update_option( 'registeredBowerFiles', $registeredBowerFiles, true );
  }

/**
 * Активация файлов в Wordpress
 * @param $files
 * @param $name
 */
  function activateFilesInWP($files, $name){
    $activatedBowerFiles = get_option('activatedBowerFiles', array());
    if(is_array($activatedBowerFiles)){
      foreach($activatedBowerFiles as $file => $params){
        if($params['module'] == $name)
          unset($activatedBowerFiles[$file]);
      }
    }
    $activatedBowerFiles = array_merge($activatedBowerFiles, $files);
    update_option( 'activatedBowerFiles', $activatedBowerFiles, true );
  }

/**
 * Удаление модуля
 */
  add_action('before_delete_post', 'delete_bower_component_files');
  function delete_bower_component_files($postid){
    $name = get_post_field('post_title', $postid, 'raw');

    $registeredBowerFiles = get_option('registeredBowerFiles', array());
    if(is_array($registeredBowerFiles)){
      $newRegisteredBowerFiles = array();
      foreach($registeredBowerFiles as $file => $params){
        if($params['module'] == $name)
          unset($newRegisteredBowerFiles[$file]);
      }
      if($newRegisteredBowerFiles != $registeredBowerFiles)
      update_option( 'registeredBowerFiles', $newRegisteredBowerFiles, true );
    }

    $activatedBowerFiles = get_option('activatedBowerFiles', array());

    if(is_array($activatedBowerFiles)){
      $newActivatedBowerFiles = array();
      foreach($activatedBowerFiles as $file => $params){
        if($params['module'] != $name)
          $newActivatedBowerFiles[$file] = $params;
      }
      if($newActivatedBowerFiles != $activatedBowerFiles)
        update_option( 'activatedBowerFiles', $newActivatedBowerFiles, true );
    }

    if(is_dir(WP_BOWER_INSTALL_DIR . '/bower_components/' . $name)) {
      fullRemove_ff(WP_BOWER_INSTALL_DIR . '/bower_components/' . $name);
    }
  }

/**
 * Рекурсивное удаление директории
 * @param string $path
 * @param string $t
 * @return string
 */
function fullRemove_ff($path,$t="1") {
  $rtrn="1";
  if (file_exists($path) && is_dir($path)) {
    $dirHandle = opendir($path);
    while (false !== ($file = readdir($dirHandle))) {
      if ($file!='.' && $file!='..') {
        $tmpPath=$path.'/'.$file;
        chmod($tmpPath, 0777);
        if (is_dir($tmpPath)) {
          fullRemove_ff($tmpPath);
        } else {
          if (file_exists($tmpPath)) {
            unlink($tmpPath);
          }
        }
      }
    }
    closedir($dirHandle);
    if ($t=="1") {
      if (file_exists($path)) {
        rmdir($path);
      }
    }
  } else {
    $rtrn="0";
  }
  return $rtrn;
}

/**
 * Вывод информации о файле
 * @param $files
 * @param bool $main
 */
  function the_bower_files($files, $main = false){

    $i = 0;
    foreach($files as $key => $file){

      if($main){
        echo bower_file_template(
          $file['name'],
          $file['full'],
          false,
          true,
          isset($file['enabled'])&&$file['enabled']=='full'?true:false,
          isset($file['site_part'])?$file['site_part']:'',
          isset($file['in_footer'])?$file['in_footer']:'',
          $i
        );
        $i++;
        echo bower_file_template(
          preg_replace('/(\.js|\.css)/',
          '.min$1',
          $file['name']),
          $file['min'],
          true,
          true,
          isset($file['enabled'])&&$file['enabled']=='min'?true:false,
          isset($file['site_part'])?$file['site_part']:'',
          isset($file['in_footer'])?$file['in_footer']:'',
          $i
        );
      }else{
        echo bower_file_template(
          $file['name'],
          $key,
          false,
          false,
          isset($file['enabled'])?$file['enabled']:false,
          isset($file['site_part'])?$file['site_part']:'',
          isset($file['in_footer'])?$file['in_footer']:'',
          $i
        );
      }

      $i++;
    }

  }

/**
 * Шаблон вывода информации о файле
 * @param string $name
 * @param string $path
 * @param bool $min
 * @param bool $main
 * @param bool $enabled
 * @param $site_part
 * @param string $in_footer
 * @param int $i
 * @return string
 */
  function bower_file_template($name, $path, $min, $main, $enabled, $site_part, $in_footer, $i){

    $str = '';

    $str .= '<div class="'.($main?'main':'').($min?' min':'').'">';

      $str .= '<input type="hidden" name="url-' . ($main?'main':'') . $i . '" value="'.$path.'">';

      $str .= '<input type="checkbox" name="file[' . ($main?'main':'') . $i . ']" id="' . $name . '" value="' . $name . '"'.($enabled?' checked':'').'><label for="' . $name . '"><b>' . $name . '</b> <small>(' . $path . ')</small></label>';

      $str .= '<table class="bower_settings" id="' . $name . '.settings">';

      $str .= '<tr><td><label for="site_part-' . $name . '">'.__('Section of the website', 'wpb').'</label></td>';
      $str .= '<td><select id="site_part-'.$name.'" name="site_part-' . ($main?'main':'') . $i . '">';
      $str .= '<option value="frontend"' . ($site_part=='frontend'?' selected':'') .'>Лицевая часть</option>';
      $str .= '<option value="backend"' . ($site_part=='backend'?' selected':'') .'>Админичтративная часть</option>';
      $str .= '<option value="login"' . ($site_part=='login'?' selected':'') . '>Страница авторизации</option></select></td></tr>';

      $str .= '<tr><td><label for="location-' . $name . '">'.__('Choose a location for the output of the script', 'wpb').'</label></td>';
      $str .= '<td><select id="location-' . $name . '" name="location-' . ($main?'main':'') . $i . '"><option value="false"' .($in_footer=='false'?' selected':''). '>'.__('Header', 'wpb').'</option><option value="true"' .($in_footer=='true'?' selected':''). '>'.__('Footer', 'wpb').'</option></select></td></tr>';

      $str .= '</table>';

    $str .= '</div>';

    return $str;

  }

/**
 * Активация метабоксов
 */
function bower_component_meta(){
  add_meta_box( 'bower_component_settings', __('Settings', 'wpb'), 'bower_component_settings_callback', '' );
  add_meta_box( 'bower_component_dependencies', __('Dependencies', 'wpb'), 'bower_component_dependencies_callback', '', 'side', 'low' );
}

/**
 * Метабокс настроек модуля
 */
  function bower_component_settings_callback(){
    global $post;

    $title = get_the_title();

    if (!empty($title)) {
      $bowerComponent = new bowerComponent(WP_BOWER_INSTALL_DIR . '/bower_components/' . $title, $post->post_content);

      $bowerComponentMainFiles = $bowerComponent->getMainFiles();
      $bowerComponentSecondaryFiles = $bowerComponent->getSecondaryFiles();
      $module_version = $bowerComponent->getVersion();

      echo '<label for="bower_components_filter">'.__('Filter', 'wpb').'</label> <select id="bower_components_filter"><option value="main" selected>'.__('Basic module files', 'wpb').'</option><option value="main min" selected>'.__('Basic module files', 'wpb').' ('.__('min', 'wpb').')</option><option value="all">'.__('All module files', 'wpb').'</option></select>';

      if(isset($module_version))
            echo '<b class="version">'.__('Version', 'wpb').': '.$module_version.'</b>';

      echo '<div id="bower_components_filter_container" class="main">';

      the_bower_files($bowerComponentMainFiles, true);
      the_bower_files($bowerComponentSecondaryFiles, false);

      echo '</div>';

    }

  }

/**
 * Метабокс зависимостей пакета
 */
  function bower_component_dependencies_callback(){
    global $post;

    $title = get_the_title();

    if (!empty($title)) {
      $bowerComponent = new bowerComponent(WP_BOWER_INSTALL_DIR . '/bower_components/' . $title, $post->post_content);

      $bowerComponentDependencies = $bowerComponent->getDependencies();

      if(is_array($bowerComponentDependencies) && count($bowerComponentDependencies) > 0){

        echo '<table class="dependencies">';
        echo '<thead><tr><td>'.__('Module', 'wpb').'</td><td>'.__('Version', 'wpb').'</td></tr></thead>';
        echo '<tbody>';

        foreach($bowerComponentDependencies as $dependence => $params){
          echo '<tr><td><b>'.$dependence.'</b></td><td>'.$params['compare'].' '.$params['version'].'</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
      }else{
        _e('There is no module dependencies', 'wpb');
      }
    }
  }

/**
 * Локализация стандартного вывода
 * @param $messages
 * @return mixed
 */
  function bower_component_updated_messages( $messages ) {
    global $post, $post_ID;

    $messages['bower_component'] = array(
      0 => '',
      1 => sprintf( __('Bower component was updated.', 'wpb').' <a href="%s">'.__('View entry Bower component', 'wpb').'</a>', esc_url( get_permalink($post_ID) ) ),
      2 => __('Bower component was updated.', 'wpb'),
      3 => __('Bower component was removed.', 'wpb'),
      4 => __('Record Bower component was updated.', 'wpb'),
      5 => isset($_GET['revision']) ? sprintf( __('Record Bower component recovered from the revision', 'wpb').' %s', wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
      6 => sprintf( __('Bower component "% s" is published.', 'wpb'), get_the_title($post_ID) ),
      7 => __('Record Bower component is stored.', 'wpb'),
      8 => sprintf( __('Record Bower component is stored.', 'wpb').' <a target="_blank" href="%s">'.__('Preview Bower component recording', 'wpb').'</a>', esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
      9 => sprintf( __('Record Bower component is scheduled for:', 'wpb').' <strong>%1$s</strong>. <a target="_blank" href="%2$s">'.__('Preview Bower component recording', 'wpb').'</a>',
        date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
      10 => sprintf( __('Draft Bower component record is updated.', 'wpb').' <a target="_blank" href="%s">'.__('Preview Bower component recording', 'wpb').'</a>', esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
    );

    return $messages;
  }
  add_filter('post_updated_messages', 'bower_component_updated_messages');

/**
 * Создаем новую колонку
 */
  add_filter('manage_edit-bower_component_columns', 'add_views_column', 4);
  function add_views_column( $columns ){
    return insert_after($columns, 'title', 'version', __('Version', 'wpb'));
  }

/**
 * Вставка нового значения в массив после определённого
 * @param array $input
 * @param $refKey
 * @param $insertKey
 * @param $insertValue
 * @return array
 */
  function insert_after(array $input, $refKey, $insertKey, $insertValue) {
    if (!isset($input[$refKey]) || isset($input[$insertKey]))
      return $input;

    $keys  = array_keys($input);
    $index = array_search($refKey, $keys);

    $result = $input;
    return array_slice($result, 0, $index + 1, true)
    + array($insertKey => $insertValue)
    + array_slice($result, $index + 1, null, true);
  }

/**
 * Заполняем колонку данными
 */
  add_filter('manage_bower_component_posts_custom_column', 'fill_views_column', 5, 2);
  function fill_views_column($column_name, $post_id) {
    if( $column_name != 'version' )
      return;

    $post = get_post( $post_id );
    if ( !empty( $post ) ) {
      echo preg_replace('/[^\.\d]/', '', $post->post_excerpt);
    }
  }

/**
 * Подправим ширину колонки через css
 */
  add_action('admin_head', 'add_version_column_css');
  function add_version_column_css(){
    echo '<style type="text/css">.column-version{width:10%;}</style>';
  }