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
 * --------------------------------------------------------------------
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * --------------------------------------------------------------------
 * TBD - INCOMPLETE
 * --------------------------------------------------------------------*/
 namespace Flussu\Persons;
use Flussu\Beans;
use Flussu;
use Flussu\General;
class User {
    protected $mId=0;
    protected $mActive=0;
    protected $mUName="";
    protected $mEmail="";
    protected $mName="";
    protected $mSurname="";
    private   $mDBPass="";
    private   $mPsChgDt;
    private   $_UBean;

    public function __construct (){
        General::addRowLog("[Costr User]");
        $this->_UBean=new \Flussu\Beans\User(General::$DEBUG);
        $this->mPsChgDt= date('Y-m-d', strtotime("-1 week", date('now')));
        $this->clear();
    }

    public function getId()          {return $this->mId;}
    public function getUserId()      {return $this->mUName;}
    public function getEmail()       {return $this->mEmail;}
    public function getName()        {return $this->mName;}
    public function getSurname()     {return $this->mSurname;}
    public function getChangePassDt(){return $this->mPsChgDt;}

    public function hasARule(){
        $UsrRul=new UsrRule(General::$DEBUG);
        $UsrRul->selectUser($this->mId);
        return $UsrRul->getc15_ruleid()>0;
    }
    public function hasPassword(){
        return $this->_UBean->getc80_password()!="";
    }
    public function mustChangePassword(){
        if ($this->mId>0){
            return strtotime($this->mPsChgDt)<strtotime(date('now'));
        } 
        return false;
    }

    public function clear(){
        $this->mId     = 0;
        $this->mActive = 0;
        $this->mUName  = "";
        $this->mEmail  = "";
        $this->mName  = "";
        $this->mDBPass = "";
        $this->mSurname   = "";
        $this->_UBean->clear();
    }

    // TBD
    // TBD
    // TBD
    public function isActive(){return true;}
    public function checkRuleLelev($neededRuleLevel){if ($this->mId>0) return true; else return false;}
    // TBD
    // TBD
    // TBD

    public function getApiCallKey($minutesValid){
        


    }
    public function authFromApiCallKey($theKey){
        

        
    }

    public function registerNew(string $userid, string $password, string $email, string $name="", string $surname=""){
        // CREATE NEW USER ON DATABASE USING USERID

        $this->_UBean->setc80_username($userid);
        $this->_UBean->setc80_email($email);
        $this->_UBean->setc80_name($name);
        $this->_UBean->setc80_surname($surname);
        $effectiveDate = date('Y-m-d', strtotime("-1 week", date('now')));
        $this->_UBean->setc80_pwd_chng($effectiveDate);

        $this->_UBean->insert();
//        $this->setNewEmailCheck();

        General::addRowLog("[Register NEW User=".$userid."] -> ".$this->_UBean->getLog());
        // GET $mId
        $this->load($userid);
        // SET PASSWORD
        if ($password!=""){
            if ($this->setPassword($password,true)){
                //$this->_UBean->setc80_pwd_chng($effectiveDate);
                //$this->_UBean->update();
                $this->load($userid);
            }
        }
    }

    public function emailExist($emailAddress){
        if (trim($emailAddress)!=""){
            $row=$this->_UBean->selectDataUsingEmail($emailAddress);
            if (is_array($row))
                return array(true,$row["UID"],$row["LOGIN_ID"]);
        }
        return array(false,0,"","1899/12/31 23:59:59");
    }

    public function load($userid){
      // LOAD FROM DATABASE
      $this->clear();
        try{
            if (is_numeric($userid))
                $this->_UBean->select($userid);
            else
                $this->_UBean->load($userid);


            //echo "UBEAN UID=".$this->_UBean->getc80_id()." ";

            if ($this->_UBean->getc80_id()>0){
                $this->mId     = (int)$this->_UBean->getc80_id();
                $this->mUName  = $this->_UBean->getc80_username();
                $this->mEmail  = $this->_UBean->getc80_email();
                $this->mDBPass = $this->_UBean->getc80_password();
                $this->mName   = $this->_UBean->getc80_name();
                $this->mSurname= $this->_UBean->getc80_surname();
                $this->mPsChgDt= $this->_UBean->getc80_pwd_chng();
                return ($this->mId>0);
            }
        } catch(\Exception $e){
            //echo "ERROR:".$e->getMessage();
            General::addRowLog("[Load User] exception".$e->getMessage());
            //$this->clear();
        }
        //echo " - [Load User ".$userid."] NOT loaded ID=".$userid;
        General::addRowLog("[Load User ".$userid."] NOT loaded ID=".$userid);
        $this->clear();
        return false;
    }
    public function authenticateToken(string $userId, string $token){
        // DA IMPLEMENTARE
        return true;
    }

    public function authenticate(string $userId, string $password){
        General::addRowLog("[Auth User]");
        // GET FROM DATABASE
        $res=$this->load($userId);
        if (!$res && General::isEmailAddress($userId)){
            // E' un indirizzo email, provare a vedere se esiste un utente con quel indirizzo email
            $ruw=$this->emailExist($userId);
            if ($ruw[0])
                $res=$this->load($ruw[2]);
        }

        if($res){
            // AUTH but MUST CHANGE PASS ---------------------
            if ($this->mId>0 && $this->mDBPass==="") return true;
            // -----------------------------------------------

            $gpwd=$this->_genPwd($this->mId, $this->mUName, $password);
            if (General::$DEBUG) $_SESSION["(debug only) AUTH using PWD"]=$gpwd;
            $authOk=($gpwd===$this->mDBPass);
            if ($authOk)
                return true;
            else
                 $this->clear();
        }
        return false;
    }
  
    public function getThumbPicPath(){
      $File=new Documents\File($this);
      $UsrImg=new Persons\UsrDoc($this,$File);
      $UsrImg->load_Type(1);
      if ($UsrImg->getFileid()>0){
            $File->load($UsrImg->getFileid());
            return $File->getThumpath();
      }
      return "/assets/images/user.png";
    }

    public function getPicInfo(){
        $pth="/assets/images/user.png";
        $thu="/assets/images/user.png";
        $typ="image/png";
        $File=new Documents\File($this);
        $UsrImg=new Persons\UsrDoc($this,$File);
        $UsrImg->load_Type(1);
        if ($UsrImg->getFileid()>0){
            $File->load($UsrImg->getFileid());
            $pth=$File->getPath();
            $typ=$File->getFtype();
            $thu=$File->getThumpath();
        }
        return array($pth,$typ,$thu);
    }
    public function getBgInfo(){
        $pth="/assets/images/userbg.jpg";
        $thu="/assets/images/userbg.jpg";
        $typ="image/jpeg";
        $File=new Documents\File($this);
        $UsrImg=new Persons\UsrDoc($this,$File);
        $UsrImg->load_Type(2);
        if ($UsrImg->getFileid()>0){
            $File->load($UsrImg->getFileid());
            $pth=$File->getPath();
            $typ=$File->getFtype();
            $thu=$File->getThumpath();
            $thu=$File->getWidth();
            $thu=$File->getHeight();
        }
        return array($pth,$typ,$thu);
    }

    public function getConnectedRows($whereClause){
        return $this->getUserList(null,true);
        //return $this->_UBean->selectRows("*",$whereClause);
    }

    public function getDisplayName(){
        return trim($this->mName)." ".trim($this->mSurname);
    }

    public function setPassword($password,$temporary=false){
        General::addLog("[Set User pwd]:");
        if ($this->mId>0){
            $this->mPass=$this->_genPwd($this->mId, $this->mUName, $password);
            if ($this->mPass!=""){
                //PUT ON DATABASE
                if ($this->mId != $this->_UBean->getc80_id())
                    $this->_UBean->select($this->mId);
                $this->_UBean->setc80_password($this->mPass);
                if ($temporary)
                    $sca=date("Y/m/d H:i:s",strtotime("-1 week"));
                else
                    $sca=date("Y/m/d H:i:s",strtotime("+1 year"));
                //$scadDate=date("Y/m/d H:i:s",$sca);
                $this->_UBean->setc80_pwd_chng( $sca );
                $done= $this->_UBean->updatePassword();
                if ($done) General::addRowLog(" done");
                if (!$done) General::addRowLog(" NOT REG ON DB!");
                return $done;
            } else {
                General::addRowLog("NOT GENERATED!");
            }
        } else {
            General::addRowLog("[NO USER]:");
        }
        return false;
    }

    private function _genPwd(int $iId, string $Uid, string $Pwd){
        General::addRowLog("[Gen Usr Pass]");
        if($iId>0 && strlen($Uid)>=4 && strlen($Pwd)>4){
            if (strlen($Uid)<16){
                if (strlen($Uid)%2==0)
                    $Uid=substr($Uid.".+-?0652743189@#",0,16);
                else
                    $Uid=substr($Uid."&@#943-1?065+27.",0,16);
            }
            if (strlen($Pwd)<strlen($Uid)){
                if (strlen($Pwd)%2==0)
                    $Pwd=substr("£".$Pwd."$%431OPqr8.+-?(06£abc$%&/)XYz|§52DEF79@#*òçèé-_ghijkLMNsTUvw",0,strlen($Uid)+1);
                else
                    $Pwd=substr("4".$Pwd."4ld0ijkM:vw|§FNsT^U7.+9431Pqr8-?(06'£èéb52Ec$%&/)XYz@#*òç-_gh",0,strlen($Uid)+1);
            }

            $PX=bin2hex(trim($Pwd));
            $aPX=str_split(trim($PX), 2);
            for ($I=1;$I<count($aPX)-1; $I++){
                $aPX[$I]=hexdec($aPX[$I]);
            }
            $fPX=hexdec($aPX[0]);
            $aPX[0]=hexdec($aPX[count($aPX)-1]);
            $aPX[count($aPX)-1]=$fPX;

            $UX=bin2hex(trim($Uid));
            $aUX=str_split(trim($UX), 2);
            for ($I=1;$I<count($aUX)-1; $I++){
                $aUX[$I]=(int)hexdec($aUX[$I]);
            }
            $fUX=hexdec($aUX[0]);
            $aUX[0]=hexdec($aUX[count($aUX)-1]);
            $aUX[count($aUX)-1]=$fUX;

            General::addRowLog("  Pass= $Pwd");
            General::addRowLog("  Uid = $Uid");
            General::addRowLog("  aPX = ".$aPX[0].",".$aPX[1].",".$aPX[2].",".$aPX[3]." -> '".$aPX[count($aPX)-1]."'");
            General::addRowLog("  aUX = ".$aUX[0].",".$aUX[1].",".$aUX[2].",".$aUX[3]." -> '".$aUX[count($aUX)-1]."'");

            $i = 0;
            $j = 0;
            $limit = 50;
            $count = count($aUX);
            $pRes  = ""; // il risultato della pass
            General::addRowLog("  mId    = $iId");
            General::addRowLog("  mUName = $Uid");
            General::addRowLog("  Passwd = $Pwd");
            General::addRowLog("  count  = $count");

            srand($iId);
            while ($i < $limit && $i < $count) {
                if ($aPX[$j]>0){
                    if ($i%2!=0)
                        srand($iId+(int)$aPX[$j]);
                    if (is_numeric($aUX[$i]))
                        $xres=dechex(rand(0,255) ^ $aUX[$i]);
                        if (strlen($xres)==1)
                            $xres="0".$xres;
                        $pRes.=$xres;
                }
                ++$i;
                ++$j;
                if ($j >= count($aPX))
                    $j=0;
            }
            //if (\General::$Debug) echo "<br>pRes=".$pRes."<hr>";
            return $pRes;
      } else {
        //if (\General::$Debug) echo "<b color=red>NO User-ID/User-Name!!!</b><br>";
      }
      return "";
    }

    public function __toString(){
      $output = 'id:'.$this->mId;
      $output .= '- name:'.$this->mUName;
      $output .= '- email:'.$this->mEmail;
      return $output;
    }
    
    static function existEmail($emailAddress){
        if (trim($emailAddress)!=""){
            $theBean=new \Flussu\Beans\User(General::$DEBUG);
            $row=$theBean->selectDataUsingEmail($emailAddress);
            if (is_array($row))
                return true;
        }
        return false;
    }

    static function changeUserPassword($userId,$newPassword){
        if (trim($userId)!=""){
            $U=new User();
            $U->load($userId);
            if ($U->getId()>0){
                return $U->setPassword($newPassword,true);
            }
        }
        return false;
    }

    static function existUsername($userName){
        if (trim($userName)!=""){
            $theBean=new \Flussu\Beans\User(General::$DEBUG);
            $theBean->load($userName);
            return $theBean->getc80_id()>0;
        }
        return false;
    }

    public function __destruct(){
      General::addRowLog("[Distr User ".$this->mId);
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