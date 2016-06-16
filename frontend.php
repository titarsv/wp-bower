<?php
global $activatedBowerFiles, $registeredBowerFiles;
$registeredBowerFiles = get_option('registeredBowerFiles', array());
$activatedBowerFiles = get_option('activatedBowerFiles', array());

function register_bower_files($site_part, $files){
  foreach($files as $path => $params){
    if(!isset($params['site_part']) || $params['site_part'] == $site_part){
      if(isset($params['in_footer']) && $params['in_footer'] == 'true' )
        $params['in_footer'] = true;
      else
        $params['in_footer'] = false;

      if($params['extension'] == 'js'){
        //wp_deregister_script( $params['name'] );
        wp_register_script( $params['name'], WP_CONTENT_URL . '/bower_components/' .$path, $params['dependencies'], $params['version'], $params['in_footer']);
      }elseif($params['extension'] == 'css'){
        wp_register_style( $params['name'], WP_CONTENT_URL . '/bower_components/' .$path, $params['dependencies'], $params['version'] );
      }

    }
  }
}

function activate_bower_files($site_part){
  global $activatedBowerFiles;

  foreach($activatedBowerFiles as $path => $params){
    if($params['site_part'] == $site_part && $params['extension'] == 'js') {
      wp_enqueue_script($params['name']);
    }elseif($params['site_part'] == $site_part && $params['extension'] == 'css'){
      wp_enqueue_style($params['name']);
    }
  }
}

function frontend_bower_files() {
  global $activatedBowerFiles, $registeredBowerFiles;
  register_bower_files('frontend', array_merge($registeredBowerFiles, $activatedBowerFiles));
  activate_bower_files('frontend');
  if( is_404() && WP_AUTOLOAD_MODULES){
    $url = $_SERVER['REQUEST_URI'];
    if(preg_match('/\.js$|\.css$/', $url)){
      $segments = explode('/', $url);
      $filename = end($segments);
      $name = preg_replace('/\.min.js$|\.min.css$|\.js$|\.css$/', '', $filename);
      chdir(WP_CONTENT_DIR);
      ob_start();
      passthru('php '.WP_PLUGIN_DIR.'/wp-bower/bowerphp.phar install '.$name);
      $out = ob_get_contents();
      ob_end_clean();

      print_r($out);

      preg_match_all("/bower\s+(\S+)\s+install/", $out, $matches, PREG_SET_ORDER);

      if(count($matches) > 0){
        foreach($matches as $component){
          $params = explode('#', $component[1]);
          save_bower_component($params[0], $params[1]);
        }
      }
    }
  }
}
function backend_bower_files() {
  global $activatedBowerFiles, $registeredBowerFiles;
  activate_bower_files('backend', array_merge($registeredBowerFiles, $activatedBowerFiles));
  activate_bower_files('backend');
}
function login_bower_files() {
  global $activatedBowerFiles, $registeredBowerFiles;
  activate_bower_files('login', array_merge($registeredBowerFiles, $activatedBowerFiles));
  activate_bower_files('login');
}

if(is_array($activatedBowerFiles)){
  add_action( 'wp_enqueue_scripts', 'frontend_bower_files', 11 );
  add_action( 'admin_enqueue_scripts', 'backend_bower_files', 11 );
  add_action( 'login_enqueue_scripts', 'login_bower_files', 11 );
}