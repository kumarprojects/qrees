<?php
/**
* Handles image upload and resizing
* version: 3.0 (01-11-2018)
*/

class Image_model extends CI_Model{
    
    public function __construct(){
        parent::__construct();
        $this->load->helper('string');
    }
    
    /**
     * Predefined image sizes (can be changed according to project requirement)
     * Modified in ver 3.0
     */
    function image_sizes($folder){
        
        $img_sizes = array();
        
        switch($folder){
            case 'group_image' :
                $img_sizes['thumbnail'] = array('width'=>300, 'height'=>300, 'folder'=>'/thumb');
                $img_sizes['medium'] = array('width'=>600, 'height'=>600, 'folder'=>'/medium');
                break;
            case 'media_photo' :
                $img_sizes['thumbnail'] = array('width'=>300, 'height'=>300, 'folder'=>'/thumb');
                $img_sizes['medium'] = array('width'=>600, 'height'=>600, 'folder'=>'/medium');
                break;
            case 'video_thumb' :
                $img_sizes['thumbnail'] = array('width'=>300, 'height'=>300, 'folder'=>'/thumb');
                $img_sizes['medium'] = array('width'=>600, 'height'=>600, 'folder'=>'/medium');
                //$img_sizes['large'] = array('width'=>1024,'height'=>768,'folder'=>'/large');
                break;    
            case 'user_avatar' :
                $img_sizes['thumbnail'] = array('width'=>420, 'height'=>241, 'folder'=>'/thumb');
                $img_sizes['medium'] = array('width'=>600, 'height'=>600, 'folder'=>'/medium');
                break;
            case 'job_photo' :
                $img_sizes['thumbnail'] = array('width'=>300, 'height'=>300, 'folder'=>'/thumb');
                $img_sizes['medium'] = array('width'=>600, 'height'=>600, 'folder'=>'/medium');
                break; 
            case 'ads_photo' :
                $img_sizes['thumbnail'] = array('width'=>300, 'height'=>300, 'folder'=>'/thumb');
                $img_sizes['medium'] = array('width'=>600, 'height'=>600, 'folder'=>'/medium');
                break; 
        }
            
        return $img_sizes;
    }
    
    /**
     * Make upload directory
     * Modified in ver 2.0
     */
    function make_dirs($folder='', $mode=DIR_WRITE_MODE, $defaultFolder='uploads/'){

        if(!@is_dir(FCPATH . $defaultFolder)){
            mkdir(FCPATH . $defaultFolder, $mode);
        }
        
        if(!empty($folder)){

            if(!@is_dir(FCPATH . $defaultFolder . '/' . $folder)){
                mkdir(FCPATH . $defaultFolder . '/' . $folder, $mode,true);
            }
        } 
    }
    
    //not used in new version -- kept for backward compatibility
    function makedirsBk($folder='', $mode=DIR_WRITE_MODE, $defaultFolder='../uploads/'){

        if(!@is_dir(FCPATH . $defaultFolder)){

            mkdir(FCPATH . $defaultFolder, $mode);
        }
        
        if(!empty($folder)){

            if(!@is_dir(FCPATH . $defaultFolder . '/' . $folder)){
                mkdir(FCPATH . $defaultFolder . '/' . $folder, $mode,true);
            }
        } 
    }
    
    /**
     * Upload image and create resized copies
     * Modified in ver 3.0
     */
    function upload_image($image, $folder, $height=768, $width=1024, $path=FALSE ){
        
        $this->make_dirs($folder);
        
        $realpath = $path ?'../uploads/':'uploads/';
        $allowed_types = "jpg|png|jpeg|gif";    
        $img_name = random_string('alnum', 16);  //generate random string for image name
        
        $img_sizes_arr = $this->image_sizes($folder);  //predefined sizes in model
        
        //We will set min height and width according to thumbnail size
        // $min_width = $img_sizes_arr['thumbnail']['width'];
        // $min_height = $img_sizes_arr['thumbnail']['height'];

        //We will set min height and width according to thumbnail size
        if($folder != 'video_thumb'){
            // $min_width = $img_sizes_arr['thumbnail']['width'];
            // $min_height = $img_sizes_arr['thumbnail']['height'];
            $min_width = $min_height = 50;
        }else{
            $min_width = $min_height = 10; //keep min height width in case of video thumb to smaller value
        }
                
        $config = array(
                'upload_path'       => $realpath.$folder,
                'allowed_types'     => $allowed_types,
                'max_size'          => "10240",   // File size limitation, initially w'll set to 10mb (Can be changed)
                'max_height'        => "4000", // max height in px
                'max_width'         => "4000", // max width in px
                'min_width'         => $min_width, // min width in px
                'min_height'        => $min_height, // min height in px
                'file_name'         => $img_name,
                'overwrite'         => FALSE,
                'remove_spaces'     => TRUE,
                'quality'           => '100%',
            );
        
        $this->load->library('upload'); //upload library
        $this->upload->initialize($config);
 
        if(!$this->upload->do_upload($image)){
            $error = array('error' => $this->upload->display_errors());
            return $error; //error in upload
        }
        
        //image uploaded successfully - We will now resize and crop this image
        
        $image_data = $this->upload->data(); //get uploaded image data
        $this->load->library('image_lib'); //Load image manipulation library
        $thumb_img = '';

        foreach($img_sizes_arr as $k=>$v){
            
            // create resize sub-folder
            $sub_folder = $folder.$v['folder'];
            $this->make_dirs($sub_folder);

            $real_path = realpath(FCPATH .$realpath .$folder);

            $resize['image_library']      = 'gd2';
            $resize['source_image']       = $image_data['full_path'];
            $resize['new_image']          = $real_path.$v['folder'].'/'.$image_data['file_name'];
            $resize['maintain_ratio']     = TRUE; //maintain original image ratio
            $resize['width']          = $v['width'];
            $resize['height']         = $v['height'];
            $resize['quality']        = '100%';
            // We need to know whether to use width or height edge as the hard-value. 
            // After the original image has been resized, either the original image width’s edge or 
            // the height’s edge will be the same as the container
            $dim = (intval($image_data["image_width"]) / intval($image_data["image_height"])) - ($v['width'] / $v['height']);
            $resize['master_dim'] = ($dim > 0)? "height" : "width";

            $this->image_lib->initialize($resize);
            $is_resize = $this->image_lib->resize();   //create resized copies
            
            //image resizing maintaining it's aspect ratio is done. Now we will start cropping the resized image
            $source_img = $real_path.$v['folder'].'/'.$image_data['file_name'];
            
            if($is_resize && file_exists($source_img)){
                
                $source_image_arr = getimagesize($source_img);
                $source_image_width = $source_image_arr[0];
                $source_image_height = $source_image_arr[1];
                
                $source_ratio = $source_image_width / $source_image_height;
                $new_ratio = $v['width'] / $v['height'];
                
                if($source_ratio != $new_ratio){
                    
                    //image cropping config
                    $crop_config['image_library'] = 'gd2';
                    $crop_config['source_image'] = $source_img;
                    $crop_config['new_image'] = $source_img;
                    $crop_config['quality'] = "100%";
                    $crop_config['maintain_ratio'] = FALSE;
                    $crop_config['width'] = $v['width'];
                    $crop_config['height'] = $v['height'];
                    
                    if($new_ratio > $source_ratio || (($new_ratio == 1) && ($source_ratio < 1))){
                        //Source image height is greater than crop image height
                        //So we need to move on vertical(Y) axis while keeping horizantal(X) axis static(0)
                        $crop_config['y_axis'] = round(($source_image_height - $crop_config['height'])/2);
                        $crop_config['x_axis'] = 0;
                    }else{
                        //Source image width is greater than crop image width
                        //So we need to move on horizontal(X) axis while keeping vertical(Y) axis static(0)
                        $crop_config['x_axis'] = round(($source_image_width - $crop_config['width'])/2);
                        $crop_config['y_axis'] = 0;
                    }
                    
                    $this->image_lib->initialize($crop_config); 
                    $this->image_lib->crop();
                    $this->image_lib->clear();
                }

                
            }
        }

        if(empty($thumb_img))
            $image_data['image_name'] = $image_data['file_name'];
        return $image_data;
    }


    // Default image genrate here
    function default_image_genrate($userName,$folder,$path=FALSE){

        $this->make_dirs($folder);

        //Use mb_substr to get the first character.
        $firstChar = strtoupper(mb_substr($userName, 0, 1, "UTF-8"));

        $realpath = $path ?'../uploads/':'uploads/';
        $img_sizes_arr =$this->image_sizes($folder);

        $real_path = realpath(FCPATH .$realpath .$folder);
        $width = 600;
        $height = 600;

        // Replace path by your own font path
        $font = FCPATH .'backend_asset/custom/fonts/Poppins-Bold.ttf';
        $fontSize = 200;

        //Create a blank image and add some text
        $im = imagecreatetruecolor($width, $height);
        $themeColor = imagecolorallocate($im, 61, 236, 151); // set theme color
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $themeColor);

        /*
         * horizontally and vertically center the text
         * calculate the x and y positions
         */
        $xPosition = (($width/2)-((imagefontwidth($fontSize)*strlen($userName))/2)-55);
        $yPosition = (($height/2)-(imagefontheight($fontSize)/2)+100);

        // Add the text
        imagettftext($im, $fontSize, 0, $xPosition, $yPosition, $white, $font, $firstChar);

        // Save the image as 'simpletext.jpg'
        $imageName = random_string('alnum', 16).'.jpeg';
        imagejpeg($im, $real_path.'/'.$imageName); //generate random string for image name

        // Free up memory
        imagedestroy($im);

        // Sub folder create
        foreach($img_sizes_arr as $k=>$v){
                
            // create resize sub-folder
            $sub_folder = $folder.$v['folder'];
            
            $this->make_dirs($sub_folder);

            $real_path = realpath(FCPATH .$realpath .$sub_folder);

            //Create a blank image and add some text
            $im = imagecreatetruecolor($v['width'], $v['height']);
            $themeColor = imagecolorallocate($im, 61, 236, 151); // set theme color
            $white = imagecolorallocate($im, 255, 255, 255);
            imagefill($im, 0, 0, $themeColor);

            /*
             * horizontally and vertically center the text
             * calculate the x and y positions
             */
            $xPosition = (($v['width']/2)-((imagefontwidth($fontSize)*strlen($firstChar))/2)-100);
            $yPosition = (($v['height']/2)-(imagefontheight($fontSize)/2)+100);

            // Add the text
            imagettftext($im, $fontSize, 0, $xPosition, $yPosition, $white, $font, $firstChar);
            
            imagejpeg($im, $real_path.'/'.$imageName); //generate random string for image name

            // Free up memory
            imagedestroy($im);
        }

        $imageArray['image_name'] = $imageName;
        return $imageArray;
    }// end function 
    
    //delete(unlink) image from folder/server
    function delete_image($path,$file){
        
        $main   = $path.$file;
        $thumb  = $path.'thumb/'.$file;
        $medium = $path.'medium/'.$file;
        $large = $path.'large/'.$file;

        if(file_exists(FCPATH.$main)):
            unlink( FCPATH.$main);
        endif;
        if(file_exists(FCPATH.$thumb)):
            unlink( FCPATH.$thumb);
        endif;
        if(file_exists(FCPATH.$medium)):
            unlink( FCPATH.$medium);
        endif;
        if(file_exists(FCPATH.$large)):
            unlink( FCPATH.$large);
        endif;
        return TRUE;
    }

}// End of class Image_model
