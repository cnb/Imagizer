<?php

i18n_merge('Imagizer') || i18n_merge('Imagizer','en_US');

# get correct id for plugin
$thisfile = basename(__FILE__, ".php");

# register plugin
register_plugin(
	$thisfile, //Plugin id
	'Imagizer', 	//Plugin name
	'1.0', 		//Plugin version
	'Alexey Rehov',  //Plugin author (nickname: Zorato)
	'http://github.com/Zorato/Imagizer', //author website
	'Fully automatical after-upload image handler', //Plugin description
	'files', //page type - on which admin tab to display
	'imagizer_settings'  //main function (administration)
);

//TODO: customizable watermark

add_action('files-sidebar','createSideMenu',array($thisfile,'Imagizer'));
add_action('file-uploaded','imagizer_handle');


function imagizer_handle(){
    $php_modules = get_loaded_extensions();
    if(!in_arrayi('gd', $php_modules)) return;

    $config=load_config();
    $file=array();
    $file=isset($_FILES['file'])?$_FILES['file']:(isset($_FILES['Filedata'])?$_FILES['Filedata']:false);
    if ($file===false) return;

    $file['type']=is_array($file['type'])?$file['type'][0]:$file['type'];
    $file['name']=is_array($file['name'])?$file['name'][0]:$file['name'];
    $file['ext'] = lowercase(pathinfo($file['name'],PATHINFO_EXTENSION));
    
    if (!defined('GSNOUPLOADIFY')) {
        $file['target'] = str_replace('//','/',(isset($_POST['path'])) ? GSDATAUPLOADPATH.$_POST['path']."/" : GSDATAUPLOADPATH);
    }
    else {
        $file['target'] = isset($_GET['path'])?tsl("../data/uploads/".str_replace('../','', $_GET['path'])):"../data/uploads/";
    }
    $file['target'].= clean_img_name(to7bit($file['name']));
    
    $image_types=array('image/jpeg','image/pjpeg','image/png','image/gif','image/bmp');
    $image_ext=array('jpg','jpeg','png','gif','bmp');
 
    if (in_array($file['type'],$image_types) || in_array($file['ext'],$image_ext) ){ 
        if(!class_exists('ImagizerImage')){
            include_once GSPLUGINPATH.'Imagizer/ImagizerImage.php';
        }

        try{
            $image = New ImagizerImage($file['target']);
        }
        catch(RuntimeException $e){
            return;
        }
        
        if ($config->size=='var'){
            if ($config->priority=='min'){ //check max first, then min
                $image->fitMax((int)$config->max->width,(int)$config->max->height);
                $image->fitMin((int)$config->min->width,(int)$config->min->height);
            }
            else { //check min first, then max
                $image->fitMin((int)$config->min->width,(int)$config->min->height);
                $image->fitMax((int)$config->max->width,(int)$config->max->height);
            }
        }
        else { //exact size
            $image->cropCenter((int)$config->exact->width,(int)$config->exact->height);
        }

        if ($config->watermark==1 && file_exists(GSPLUGINPATH.'Imagizer/watermark.png')){
            $image->applyWatermark(GSPLUGINPATH.'Imagizer/watermark.png');
        }

        $image->save(
            $file['target'],
            $config->convert_to_jpeg==1 ? IMAGETYPE_JPEG : $image->getType(),
            $config->compress==1 ? (int)$config->compress_level : 80
        );

    }
}

function imagizer_settings(){
    $config=load_config();
    include GSPLUGINPATH.'Imagizer/settings_handler.php';
    save_config($config);
    include GSPLUGINPATH.'Imagizer/settings_viewer.php';
}

function load_config(){
    if(!file_exists(GSPLUGINPATH.'Imagizer/config.xml'))
        $c=false;
    else
        $c=@getXML(GSPLUGINPATH.'Imagizer/config.xml');
    if (!$c){ //default configuration
        $c = new SimpleXMLExtended('<?xml version="1.0"?><config></config>');
        $c->ratio='save';
        $c->size='var';
        $c->priority='max';
        $c->max->height = 1000;
        $c->max->width  = 1000;
        $c->min->height = 0;
        $c->min->width  = 0;
        $c->exact->height=300;
        $c->exact->width =300;
        $c->convert_to_jpeg=0;
        $c->watermark=0;
        $c->compress=0;
        $c->compress_level=80;
        save_config($c);
    }
    if((int)$c->compress_level === 0){
        $c->compress_level = 80;
        save_config($c);
    }
    return $c;
}

function save_config(SimpleXMLExtended $c){
    XMLsave($c,GSPLUGINPATH.'Imagizer/config.xml');
}