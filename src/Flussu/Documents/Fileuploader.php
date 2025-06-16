<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 
 Attraverso questa classe di "utility" viene gestito l'upload
 dei file richiesti nel processo. Se il file caricato è una
 immagine, viene richiamata la classe [Res_img] per gestire
 la manipolazione dell'imagine prima della sua registrazione
 sul database/file system
  
 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Documents
 * CLASS-NAME:       Fileuploader
 * -------------------------------------------------------*
 * CREATED DATE:    06.03.2021
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/

/**
 * The Fileuploader class is responsible for managing the file upload process within the Flussu server.
 * 
 * This class handles the uploading of files required in various processes. If the uploaded file is an image,
 * it utilizes the Res_img class to manipulate the image before saving it to the database or file system.
 * 
 * Key responsibilities of the Fileuploader class include:
 * - Managing the physical location where files are uploaded.
 * - Handling the upload process and ensuring files are correctly saved to the specified directory.
 * - Integrating with the Res_img class to process and manipulate images before storage.
 * - Ensuring that uploaded files are stored securely and efficiently.
 * - Providing utility functions for file handling and management within the Flussu server.
 * 
 * The class is designed to be flexible and extendable, allowing for the addition of new file types and
 * processing methods as needed.
 * 
 */

namespace Flussu\Documents;

use Flussu\General;
use Flussu\Documents\Res_img;

class Fileuploader {

    private $_filePhisicalPosition="/Uploads";
    private $_uDir="";//$_SERVER['DOCUMENT_ROOT'].$filePhisicalPosition;
    private $_localMagickPath="\"C:\\Program Files\\ImageMagick-7.0.10-Q16\\magick\"";
    private $_allowed="jpg,jpeg,gif,png,pdf,svg";
    private $_maxsize=(1024*10000); // 10MB

    public function __construct ($OverrideFilePath=null){
        if (!is_null($OverrideFilePath))
            $this->_filePhisicalPosition=$OverrideFilePath;
        $this->_uDir=$_SERVER['DOCUMENT_ROOT'].$this->_filePhisicalPosition;
    }

    public static function sanitize_filename($name) {
        // remove illegal file system characters https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        $name = str_replace(array_merge(
            array_map('chr', range(0, 31)),
            array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
        ), '', $name);
        // maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name= mb_strcut(pathinfo($name, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($name)) . ($ext ? '.' . $ext : '');
        return $name;
    }

/*
    function doUploadWorkout($destDir,$maxsize,$allowed){
        $localMagickPath="\"C:\\Program Files\\ImageMagick-7.0.10-Q16\\magick\"";

        $arrRes=array();
        if ($allowed=="")
            $allowed="jpg,jpeg,gif,png,pdf";
        $faExt=str_getcsv($allowed);
        if ($maxsize<1)
            $maxsize=(1024*8000);

        //if (isset($_POST["submit"])){
        if (isset($_FILES['files']) && is_array($_FILES['files'])){
            $filename=array_filter($_FILES['files']['name']);
            if (!empty($filename)){
                $filess=sane_file_array($_FILES);
                foreach($filess as $files){
                    foreach($files as $file){
                        $res=imageUpload($file, $destDir,$maxsize,$faExt,$localMagickPath);
                        array_push($arrRes,$res);
                    }
                }
            }
        } else {
            if (isset($_FILES['file'])){
                $res=imageUpload($_FILES['file'],$destDir,$maxsize,$faExt,$localMagickPath);
                array_push($arrRes,$res);
            }
        }
        return $arrRes;
    }
/*
    function sane_file_array($files) {
        $result = array();
        $name = array();
        $type = array();
        $tmp_name = array();
        $error = array();
        $size = array();
        foreach($files as $field => $data) {
            foreach($data as $key => $val) {
                $result[$field] = array();
                if(!is_array($val)) {
                    $result[$field] = $data;
                } else {
                    $res = array();
                    $this->files_flip($res, array(), $data);
                    $result[$field] += $res;
                }
            }
        }
        return $result;
    }

    function array_merge_recursive2($paArray1, $paArray2) {
        if (!is_array($paArray1) or !is_array($paArray2)) { return $paArray2; }
        foreach ($paArray2 AS $sKey2 => $sValue2) {
            $paArray1[$sKey2] = array_merge_recursive2(@$paArray1[$sKey2], $sValue2);
        }
        return $paArray1;
    }

    function files_flip(&$result, $keys, $value) {
        if(is_array($value)) {
            foreach($value as $k => $v) {
                $newkeys = $keys;
                array_push($newkeys, $k);
                files_flip($result, $newkeys, $v);
            }
        } else {
            $res = $value;
            // Move the innermost key to the outer spot
            $first = array_shift($keys);
            array_push($keys, $first);
            foreach(array_reverse($keys) as $k) {
                // You might think we'd say $res[$k] = $res, but $res starts out not as an array
                $res = array($k => $res);
            }

            $result = $this->array_merge_recursive2($result, $res);
        }
    }
*/

    function imageUpload($fup, $uDir="", $maxsize=-1, $allowed=""){
        $resObj=new \stdClass();
        if ($maxsize<1)
            $maxsize=$this->_maxsize;
        if (trim($allowed)=="")
            $allowed=$this->_allowed;
        $faExt=str_getcsv($allowed);

        $lMgkPath=$this->_localMagickPath;
        if (trim($uDir)=="")
            $uDir=$this->_uDir;
        //$file=$_FILES['file'];
        //print_r($fup);
    


        $resObj->fileName=$fup->fileName;
        $resObj->fileNameTmp=$fup->tmpName;
        $resObj->fileSize=$fup->fileSize;
        //$fError=$fup['error'];
        $resObj->fileType=$fup->mimeType;
        //echo "<hr>".$resObj->fileNameTmp."<br>";
        $fParts=explode('.',$resObj->fileName);
        $resObj->fileExt=strtolower(end($fParts));
    
        //MimeType Exception
        if ($resObj->fileExt=="flv" && $resObj->fileType=="application/octet-stream")
            $resObj->fileType="video/x-flv";
    
        
        $resObj->fileExt=$resObj->fileExt;

        General::addRowLog("fName=".$resObj->fileName);
        General::addRowLog("fSize=".$resObj->fileSize);
        General::addRowLog("fType=".$resObj->fileType);
        General::addRowLog("fExt=".$resObj->fileExt);
            
        if (in_array($resObj->fileExt,$faExt)){
            if ($resObj->fileSize<($maxsize)){
                if ($resObj->fileExt=="jpeg") 
                    $resObj->fileExt="jpg";
                $un=str_replace(".","-",uniqid("",true));
                substr($un,0,4)."-".substr($un,5);
                $resObj->fileNameNew=$un.".".$resObj->fileExt;
                $resObj->fileNameNew2=$un.".th.".$resObj->fileExt;
    
                $target = $uDir;
                $resObj->fileDest=$target.$resObj->fileNameNew;
    
                //echo "fDest=".$resObj->fileDest."<br>\r\n";
                $fOrig=$resObj->fileNameTmp.".cmp.".$resObj->fileExt;
                //echo "fOrig=".$fOrig."<br>\r\n";
    
                $image=null;
                if ($this->__canShrink($resObj->fileType)){
                    //use Respimg as Respimg;
                    $image = new Res_img($resObj->fileNameTmp);
                    $resObj->cd=str_replace(".",":",date("Y-m-d")."T".date("H:i:sP"));
        
                    //echo $resObj->fileNameTmp.": "; print_r($image->getImageProperties()); echo "<br>";
                    //echo $resObj->fileName."<br>";
        
                    if (!is_null($image->getImageProperty("exif:DateTimeOriginal")) && $image->getImageProperty("exif:DateTimeOriginal")!="")
                        $resObj->cd=$image->getImageProperty("exif:DateTimeOriginal");
                    elseif (!is_null($image->getImageProperty("exif:DateTimeDigitized")) && $image->getImageProperty("exif:DateTimeDigitized")!="")
                        $resObj->cd=$image->getImageProperty("exif:DateTimeDigitized");
                    elseif (!is_null($image->getImageProperty("exif:DateTime")) && $image->getImageProperty("exif:DateTime")!="")
                        $resObj->cd=$image->getImageProperty("exif:DateTime");
                    elseif (!is_null($image->getImageProperty("date:create")) && $image->getImageProperty("date:create")!="")
                        $resObj->cd=$image->getImageProperty("date:create");
                    if (strpos($resObj->cd,":")<6){
                        //$resObj->cd=str_replace(":","-",substr($resObj->cd,0,10))."T".substr($resObj->cd,11);
                        $resObj->cd=str_replace(":","-",substr($resObj->cd,0,10))." ".substr($resObj->cd,11);
                    }
        
                    $resObj->fileInfo="";
                    $resObj->fileInfos=array("Make","Model","Orientation","FNumber","FocalLength","DigitalZoomRatio","SubjectDistanceRange","ISOSpeedRatings","LightSource","MeteringMode","WhiteBalance","Flash","ExposureBiasValue","ExposureMode","ExposureProgram","ExposureTime","Software");
                    for ($i=0; $i<count($resObj->fileInfos);$i++) {
                        if (!is_null($image->getImageProperty("exif:".$resObj->fileInfos[$i])) && $image->getImageProperty("exif:".$resObj->fileInfos[$i])!="")
                        $resObj->fileInfo.=$resObj->fileInfos[$i].":".$image->getImageProperty("exif:".$resObj->fileInfos[$i])."|";
                    }
                    $resObj->fileInfos=array("manufacturer","model","copyright");
                    for ($i=0; $i<count($resObj->fileInfos);$i++) {
                        if (!is_null($image->getImageProperty("icc:".$resObj->fileInfos[$i])) && $image->getImageProperty("icc:".$resObj->fileInfos[$i])!="")
                        $resObj->fileInfo.=$resObj->fileInfos[$i].":".$image->getImageProperty("icc:".$resObj->fileInfos[$i])."|";
                    }
                    if ($resObj->fileInfo!="")
                        $resObj->fileInfo=substr($resObj->fileInfo,0,strlen($resObj->fileInfo)-1);
                    //echo $resObj->fileInfo."<br>";   
                    $orientation = $image->getImageOrientation();
                    $resObj->isRotated="0";
                    switch ($orientation) {
                        case \Imagick::ORIENTATION_TOPRIGHT:
                            // flipped
                        // flipped
                        case \Imagick::ORIENTATION_UNDEFINED:
                            // undefined
                        // undefined
                        case \Imagick::ORIENTATION_TOPLEFT:
                            // normal
                            break;
                        case \Imagick::ORIENTATION_BOTTOMLEFT:
                            // 180° flipped
                        // 180° flipped
                        case \Imagick::ORIENTATION_BOTTOMRIGHT:
                            // 180°
                            $resObj->isRotated="180";
                            $image->rotateImage("#000",-180);
                            $image->setImageOrientation(1);
                            break;
                        case \Imagick::ORIENTATION_LEFTTOP:
                            // 270° flipped
                        // 270° flipped
                        case \Imagick::ORIENTATION_RIGHTTOP:
                            // 270°
                            $resObj->isRotated="270";
                            $image->rotateImage("#000",-270);
                            $image->setImageOrientation(1);
                            break;
                        case \Imagick::ORIENTATION_RIGHTBOTTOM:
                            // 90° flipped
                        // 90° flipped
                        case \Imagick::ORIENTATION_LEFTBOTTOM:
                            // 90°
                            $resObj->isRotated="90";
                            $image->rotateImage("#000",-90);
                            $image->setImageOrientation(1);
                            break;
                    }
                    $resObj->ow=$image->getImageWidth();
                    $resObj->oh=$image->getImageHeight();
                    if ($resObj->ow>850 || $resObj->oh>850){
                        // Se l'immagine è troppo grande
                        $r=850;
                        //resize altezza o larghezza
                        //echo "Resize:";
                        if ($resObj->ow>=$resObj->oh){
                            if ($resObj->ow/3>850)
                                $r=round($resObj->ow/3,0);
                            //echo "W-";
                            $image->smartResize($r, 0, false);
                            //echo "OK";
                        }
                        if ($resObj->oh>$resObj->ow){
                            if ($resObj->oh/3>850)
                                $r=round($resObj->oh/3,0);
                            //echo "H(0,$r,false) ";
                            $image->smartResize(0,$r, false);
                            //echo "OK";
                        }
                    } else {
                        //altrimenti il resize serve solo alla compressione
                        //echo "Smart-";
                        $image->smartResize($resObj->ow-1, 0, false);
                        //echo "OK";
                    }
                    //echo ".<br>";
                    $image->writeImage($resObj->fileDest);
                    $resObj->rw=$image->getImageWidth();
                    $resObj->rh=$image->getImageHeight();
                    $resObj->rs=filesize ($resObj->fileDest);
        
                    $resObj->fileDest2=$target.$resObj->fileNameNew2;
                    //$image->setImageFormat('jpg');
                    $image->smartResize(120, 0, false);
                    $image->writeImage($resObj->fileDest2);
                } else {
                    /*
                    $path_parts = pathinfo($resObj->fileNameTmp);
                    $newF=$path_parts['filename'];
                    $newfTmpName="D:\\xampp\\tmp\\uploads\\".$newF.".".$resObj->fileExt;
                    */
                    //move_uploaded_file($resObj->fileNameTmp, $resObj->fileDest);
                    if (rename($resObj->fileNameTmp, $resObj->fileDest)){
                        $resObj->fileNameTmp=$resObj->fileDest;
                        $resObj->fileDest2=$target.$un.".th.jpg";
                    }
                    if ($this->__canThumbnail($resObj->fileType)){
                        General::addRowLog("File Temp Name=$resObj->fileNameTmp");
                        //$resObj->fileDest2=$target.$resObj->fileNameNew2;
                        General::addRowLog("Thumbnail Name=$resObj->fileDest2");
                        if (is_null($image)){
                            $image = new \Imagick($resObj->fileNameTmp);
                        }
                        $image->setImageFormat('jpg');
                        $image->setResolution(100, 120);
                        //$image->readImage(sprintf('%s[%s]', $resObj->fileNameTmp, 1));
                        //$image->thumbnail(100, 120);
                        $image->writeImage($resObj->fileDest);
                        $resObj->fileNameNew2=basename($resObj->fileDest);
        
                        $resObj->fileNameDest=$uDir.basename($resObj->fileNameNew);
                        //echo "<hr>fTmpName=$resObj->fileNameTmp<br>fNameDest=$resObj->fileNameDest<br>uDir=$uDir<br>fName=$resObj->fileName<br>fNameNew=$resObj->fileNameNew<br>fNameNew2=$resObj->fileNameNew2<br>fDest2=$resObj->fileDest2<hr>";
                        if (copy($resObj->fileNameTmp, $resObj->fileNameDest))
                            unlink($resObj->fileNameTmp);
                        //die();
                    } else if ($this->__canPdfThumb($resObj->fileType)){
                        $resObj->fileDest2=$target.$un.".th.jpg";
                        $resObj->fileNameNew2=basename($resObj->fileDest2);
                        $this->makePdfThumb($lMgkPath,$resObj->fileNameTmp,$resObj->fileDest2);
                        $resObj->fileNameDest=$uDir.basename($resObj->fileNameNew);
                        if ($resObj->fileNameTmp!=$resObj->fileNameDest && copy($resObj->fileNameTmp, $resObj->fileNameDest))
                            unlink($resObj->fileNameTmp);
                    } else if ($this->__canMovieThumb($resObj->fileType)){
                        $resObj->fileDest2=$target.$un.".th.jpg";
                        $resObj->fileNameNew2=basename($resObj->fileDest2);
                        $this->makeMovieThumb($lMgkPath,$resObj->fileNameTmp,$resObj->fileDest2);
                        $resObj->fileNameDest=$uDir.basename($resObj->fileNameNew);
                        //echo "<hr>fTmpName=$resObj->fileNameTmp<br>fNameDest=$resObj->fileNameDest<br>uDir=$uDir<br>fName=$resObj->fileName<br>fNameNew=$resObj->fileNameNew<br>fNameNew2=$resObj->fileNameNew2<br>fDest2=$resObj->fileDest2<hr>";
                        if (copy($resObj->fileNameTmp, $resObj->fileNameDest))
                            unlink($resObj->fileNameTmp);
                        //die();
                    }
                }
                //echo "MakeThumb:";
                if ($resObj->fileDest2=="")
                    $resObj->fileDest2=$this->__getThumb($resObj->fileType);
                //echo "Ok<br>";
                
                return $resObj;
            } else {
                return array($resObj->fileName,0,"File is too big. Max ".$maxsize." bytes");
            }
        } else {
            return array($resObj->fileName,2,"Cannot accept upload: allowed files are:".implode(",",$faExt));
        }
    }

    function makePdfThumb($lMgkPath,$file,$thumb) {
        // IL NOME DEL FILE DEVE AVERE ESTENSIONE COME IL FILE ORIGINALE; ES:.MOV O SIMILE
        $cmd=$lMgkPath." -density 150 \"".$file."[0]\" -background white -flatten -trim -quality 75 -resize 200 \"".$thumb."\"";
        //echo "<hr>$cmd<hr>";
        exec($cmd, $output, $return_var);
        //die();
    }

    function makeMovieThumb($lMgkPath,$file,$thumb) {
        // IL NOME DEL FILE DEVE AVERE ESTENSIONE COME IL FILE ORIGINALE; ES:.MOV O SIMILE
        $cmd=$lMgkPath." convert \"".$file."[20]\" -flatten -trim -quality 75 -resize 200 \"".$thumb."\"";
        //echo "<hr>$cmd<hr>";
        exec($cmd, $output, $return_var);
        //die();
    }

    private function __canThumbnail ($mimetype) {
        switch(strtolower($mimetype)){
            case 'image/gif';
            case 'image/tif';
            case 'image/tiff';
            case 'image/bmp';
            case 'image/jp2';
            case 'image/jpeg';
            case 'image/jpeg2000';
            case 'image/pdf';
            case 'image/jpeg2000-image';
            case 'image/pjpeg';
            case 'image/png';
                return true;
                break;
        }
        return false;
    }

    private function __canPdfThumb ($mimetype) {
        switch(strtolower($mimetype)){
            case 'application/pdf';
            case 'application/acrobat';
            case 'application/nappdf';
            case 'application/x-pdf';
                return true;
                break;
        }
        return false;
    }

    private function __canMovieThumb ($mimetype) {
        switch(strtolower($mimetype)){
            case 'video/mpg';
            case 'video/mkv';
            case 'video/m4v';
            case 'video/mp4';
            case 'video/mov';
            case 'video/x-flv';
            case 'video/avi';
                return true;
                break;
        }
        return false;
    }

    private function __canShrink($mimetype) {
        switch(strtolower($mimetype)){
            case 'image/jp2';
            case 'image/jpeg';
            case 'image/jpeg2000';
            case 'image/jpeg2000-image';
            case 'image/pjpeg';
                return true;
                break;
        }
        return false;
    }

    private function __getThumb($mimetype){
        $ret="";
        switch(strtolower($mimetype)){
            case 'application/vnd.lotus-1-2-3';
            case 'application/vnd.lotus-approach';
            case 'application/vnd.lotus-freelance';
            case 'application/vnd.lotus-notes';
            case 'application/vnd.lotus-organizer';
            case 'application/vnd.lotus-screencam';
            case 'application/lotus123';
            case 'application/x-123';
            case 'application/wk1';
            case 'application/x-lotus123';
                $ret="lotus.png";
                break;
            case 'application/vnd.geoplan';
            case 'application/vnd.geospace';
            case 'application/vnd.google-earth.kml+xml';
            case 'application/vnd.google-earth.kmz';
                $ret="geospace.png";
                break;
            case 'application/msexcel';
            case 'application/x-msexcel';
            case 'application/vnd.ms-excel';
            case 'application/vnd.ms-excel.addin.macroenabled.12';
            case 'application/vnd.ms-excel.sheet.binary.macroenabled.12';
            case 'application/vnd.ms-excel.sheet.macroenabled.12';
            case 'application/vnd.ms-excel.template.macroenabled.12';
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.template';
            case 'application/vnd.oasis.opendocument.spreadsheet';
            case 'application/vnd.oasis.opendocument.spreadsheet-flat-xml';
            case 'application/vnd.oasis.opendocument.spreadsheet-template';
            case 'application/vnd.oasis.opendocument.formula';
            case 'application/vnd.oasis.opendocument.formula-template';
                $ret="spreadsheet.png";
                break;
            case 'application/powerpoint';
            case 'application/mspowerpoint';
            case 'application/vnd.ms-powerpoint';
            case 'application/x-mspowerpoint';
            case 'application/vnd.ms-publisher';
            case 'application/x-mspublisher';
            case 'application/vnd.ms-powerpoint.addin.macroenabled.12';
            case 'application/vnd.ms-powerpoint.presentation.macroenabled.12';
            case 'application/vnd.ms-powerpoint.slide.macroenabled.12';
            case 'application/vnd.ms-powerpoint.slideshow.macroenabled.12';
            case 'application/vnd.ms-powerpoint.template.macroenabled.12';
            case 'application/vnd.oasis.opendocument.presentation';
            case 'application/vnd.oasis.opendocument.presentation-flat-xml';
            case 'application/vnd.oasis.opendocument.presentation-template';
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            case 'application/vnd.openxmlformats-officedocument.presentationml.slide';
            case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow';
            case 'application/vnd.openxmlformats-officedocument.presentationml.template';
            case 'application/vnd.quark.quarkxpress';
                $ret="presentation.png";
                break;
            case 'application/vnd.ms-visio.drawing.macroenabled.main+xml';
            case 'application/vnd.ms-visio.drawing.main+xml';
            case 'application/vnd.ms-visio.stencil.macroenabled.main+xml';
            case 'application/vnd.ms-visio.stencil.main+xml';
            case 'application/vnd.ms-visio.template.macroenabled.main+xml';
            case 'application/vnd.ms-visio.template.main+xml';
            case 'application/vnd.oasis.opendocument.graphics';
            case 'application/vnd.oasis.opendocument.graphics-flat-xml';
            case 'application/vnd.oasis.opendocument.graphics-template';
            case 'application/vnd.ms-artgalry';
            case 'application/vnd.oasis.opendocument.image';
            case 'application/vnd.oasis.opendocument.image-template';
                $ret="graphics.png";
                break;
            case 'application/msword';
            case 'application/x-msword';
            case 'application/x-mswrite';
            case 'application/msword-template';
            case 'application/wordperfect';
            case 'application/vnd.ms-word';
            case 'application/vnd.ms-word.document.macroenabled.12';
            case 'application/vnd.ms-word.template.macroenabled.12';
            case 'application/vnd.oasis.opendocument.text';
            case 'application/vnd.oasis.opendocument.text-flat-xml';
            case 'application/vnd.oasis.opendocument.text-master';
            case 'application/vnd.oasis.opendocument.text-template';
            case 'application/vnd.oasis.opendocument.text-web';
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template':
            case 'application/x-wordperfect';
            case 'application/x-pagemaker';
                $ret="wordprocess.png";
                break;
            case 'application/pkcs10';
            case 'application/pkcs12';
            case 'application/pkcs7-mime';
            case 'application/pkcs7-signature';
            case 'application/pkcs8';
            case 'application/pkcs8-encrypted';
            case 'application/pkix-attr-cert':
            case 'application/pkix-cert';
            case 'application/pkix-crl';
            case 'application/pkix-pkipath';
            case 'application/pkixcmp';
            case 'application/vnd.ms-pki.seccat';
            case 'application/vnd.ms-pki.stl';
                $ret="crypto.png";
                break;
            case 'application/vnd.rar';
            case 'application/x-bzip';
            case 'application/x-bzip-compressed-tar';
            case 'application/x-bzip2';
            case 'application/x-bzpdf';
            case 'application/x-compress';
            case 'application/x-compressed-tar';
            case 'application/x-gtar';
            case 'application/x-gzdvi';
            case 'application/x-gzip';
            case 'application/x-gzpdf';
            case 'application/x-lha';
            case 'application/x-lhz';
            case 'application/x-lrzip';
            case 'application/x-lrzip-compressed-tar';
            case 'application/x-lz4';
            case 'application/x-lz4-compressed-tar';
            case 'application/x-lzh-compressed';
            case 'application/x-lzip';
            case 'application/x-lzip-compressed-tar';
            case 'application/x-lzma';
            case 'application/x-lzma-compressed-tar';
            case 'application/vnd.ms-cab-compressed';
            case 'application/x-zoo';
            case 'application/x-lzop';
            case 'application/x-lzpdf';
            case 'application/x-tar';
            case 'application/x-tarz';
            case 'application/x-xz';
            case 'application/x-xz-compressed-tar';
            case 'application/x-xzpdf';
            case 'application/x-zip';
            case 'application/x-zip-compressed';
            case 'application/x-zip-compressed-fb2';
            case 'application/zip';
            case 'application/zlib';
            case 'application/x-gzpostscript';
                $ret="compressed.png";
                break;
            case 'application/x-font-afm';
            case 'application/x-font-bdf';
            case 'application/x-font-ghostscript';
            case 'application/x-font-linux-psf';
            case 'application/x-font-otf';
            case 'application/x-font-pcf';
            case 'application/x-font-snf';
            case 'application/x-font-speedo';
            case 'application/x-font-ttf';
            case 'application/x-font-ttx';
            case 'application/x-font-type1';
            case 'application/x-font-woff';
            case 'application/vnd.ms-fontobject';
            case 'application/x-gz-font-linux-psf';
                $ret="font.png";
                break;
            case 'application/vnd.rn-realmedia';
            case 'application/vnd.rn-realmedia-vbr';
                $ret="audio.png";
                break;
            case 'application/x-msaccess';
            case 'application/x-mdb';
            case 'application/msaccess';
            case 'application/vnd.msaccess';
            case 'application/vnd.ms-access';
            case 'application/vnd.oasis.opendocument.database' ;
                $ret="database.png";
                break;
            case 'application/x-ms-dos-executable';
            case 'application/x-msdownload';
            case 'application/x-ms-application';
            case 'application/x-msi';
                $ret="application.png";
                break;
            case 'application/x-windows-themepack';
            case 'application/x-msmoney';
            case 'application/x-msschedule';
            case 'application/x-msterminal';
            case 'application/x-mswinurl';
            case 'application/x-mscardfile';
            case 'application/x-msclip';
            case 'application/vnd.ms-xpsdocument';
            case 'application/vnd.ms-officetheme';
            case 'application/vnd.ms-htmlhelp';
            case 'application/vnd.ms-project';
            case 'application/vnd.openofficeorg.extension';
                $ret="msoffice.png";
                break;
            case 'application/vnd.ms-outlook';
            case 'message/rfc822';
                $ret="email.png";
                break;
            case 'application/vnd.oasis.opendocument.chart';
            case 'application/vnd.oasis.opendocument.chart-template';
                $ret="chart.png";
                break;
            case 'application/vnd.oasis.docbook+xml';
            case 'application/epub+zip';
            case 'application/vnd.amazon.ebook';
                $ret="ebook.png";
                break;
            case 'application/x-bittorrent';
                $ret="torrent.png";
                break;
            case 'application/x-ms-shortcut';
                $ret="shortcut.png";
                break;
            case 'application/vnd.android.package-archive';
                $ret="android.png";
                break;
            case 'application/x-blender';
                $ret="blender.png";
                break;
            case 'application/x-coreldraw';
                $ret="coreldraw.png";
                break;
        }
        if ($ret=="" && substr(strtolower($mimetype), 0, 5) === "audio"){
            $ret="audio.png";
        } elseif ($ret=="" && substr(strtolower($mimetype), 0, 5) === "image"){
            $ret="image.png";
        } elseif ($ret=="" && substr(strtolower($mimetype), 0, 4) === "text"){
            $ret="text.png";
        } elseif ($ret=="" && substr(strtolower($mimetype), 0, 5) === "video"){
            $ret="video.png";
        }
        if ($ret=="")
            $ret="default.png";
        return $ret;
    }
}
 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //--------------- 