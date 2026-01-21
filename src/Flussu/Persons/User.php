<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
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
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11:2025 
 * --------------------------------------------------------------------
 * TBD - INCOMPLETE
 * --------------------------------------------------------------------*/
namespace Flussu\Persons;
use Flussu\Beans;
use Flussu;
use Flussu\General;
use Flussu\Flussuserver\NC\HandlerUserNC;
class User {
    protected $mId=0;
    protected $mActive=0;
    protected $mUName="";
    protected $mEmail="";
    protected $mName="";
    protected $mSurname="";
    private   $mDBPass="";
    private   $mPsChgDt;
    //private   $_UBean;
    private   $_uHandler;
    private   $_userData;

    public function __construct (){
        General::addRowLog("[Costr User]");
        //$this->_UBean=new \Flussu\Beans\User(General::$DEBUG);
        $this->mPsChgDt= date('Y-m-d', strtotime("-1 week", date('now')));
        $this->clear();
        $this->_uHandler=new HandlerUserNC();
        $this->_userData=null;
    }

    public function getId()          {return $this->mId;}
    public function getUserId()      {return $this->mUName;}
    public function getEmail()       {return $this->mEmail;}
    public function getName()        {return $this->mName;}
    public function getSurname()     {return $this->mSurname;}
    public function getChangePassDt(){return $this->mPsChgDt;}

    /*
    public function hasARule(){
        $UsrRul=new UsrRule(General::$DEBUG);
        $UsrRul->selectUser($this->mId);
        return $UsrRul->getc15_ruleid()>0;
    }*/
    public function hasPassword(){
        return $this->_userData["c80_password"]!="";
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
    }

    /**
     * Check if user is active (not deleted)
     * @return bool True if user is active, false otherwise
     */
    public function isActive(){
        if ($this->mId>0){
            // User is active if not deleted (deleted date is default '1899-12-31 23:59:59')
            $deletedDate = $this->_userData["c80_deleted"];
            return ($deletedDate == '1899-12-31 23:59:59' || strtotime($deletedDate) < strtotime('1900-01-01'));
        }
        return false;
    }

    /**
     * Check if user has required rule level
     * @param int $neededRuleLevel The minimum rule level required
     * @return bool True if user has required level, false otherwise
     */
    public function checkRuleLelev($neededRuleLevel){
        if ($this->mId>0){
            $userRole = $this->_UBean->getc80_role();
            return ($userRole >= $neededRuleLevel);
        }
        return false;
    }

    /**
     * Generate a temporary API call key valid for specified minutes
     * @param int $minutesValid Number of minutes the key should be valid
     * @return string|false The generated API key or false on failure
     */
    public function getApiCallKey($minutesValid){
        General::addRowLog("[Gen API Key]");
        if ($this->mId <= 0 || !$this->isActive()){
            General::addRowLog("[Gen API Key] User not loaded or inactive");
            return false;
        }

        // Use HandlerUserNC for API key generation
        return $this->_uHandler->generateApiKey($this->mId, $minutesValid);
    }

    /**
     * Authenticate user from temporary API call key
     * @param string $theKey The API key to authenticate
     * @return bool True if authentication successful, false otherwise
     */
    public function authFromApiCallKey($theKey){
        General::addRowLog("[Auth from API Key]");
        $this->clear();

        if (empty($theKey)){
            General::addRowLog("[Auth from API Key] Empty key");
            return false;
        }

        // Use HandlerUserNC for API key validation
        $keyData = $this->_uHandler->validateApiKey($theKey);

        if ($keyData === false){
            return false;
        }

        // Mark key as used
        if (!$this->_uHandler->markApiKeyAsUsed($keyData['id'])){
            General::addRowLog("[Auth from API Key] Failed to mark key as used");
            return false;
        }

        // Load the user
        $userId = (int)$keyData['user_id'];
        if ($this->load($userId)){
            if ($this->isActive()){
                General::addRowLog("[Auth from API Key] Success - User ID: {$userId}");
                return true;
            } else {
                General::addRowLog("[Auth from API Key] User inactive");
                $this->clear();
            }
        } else {
            General::addRowLog("[Auth from API Key] Failed to load user");
        }

        return false;
    }

    /**
     * Clean up expired API keys from database (static utility method)
     * @return int Number of deleted keys
     */
    static function cleanExpiredApiKeys(){
        General::addRowLog("[Clean Expired API Keys]");

        // Use HandlerUserNC for cleaning expired API keys
        $handler = new HandlerUserNC();
        return $handler->cleanExpiredApiKeys();
    }

    public function registerNew(string $userid, string $password, string $email, string $name="", string $surname=""){
        // CREATE NEW USER ON DATABASE USING USERID
        $data=[];
        $data['username']=$email;
        $data['email']=$email;
        $data['password']=$password;
        $data['name']=$name;
        $data['surname']=$surname;
        $id=$this->_uHandler->createUser($data);

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
            return $this->_uHandler->emailExists($emailAddress);
        }
        return false;
    }

    public function load($userid){
        // LOAD FROM DATABASE
        $this->clear();
        if (is_numeric($userid)){
            $userid=(int)$userid;
            try{
                $this->_userData=$this->_uHandler->getUserById($userid);
            } catch(\Exception $e){
                //echo "ERROR:".$e->getMessage();
                General::addRowLog("[Load User] exception".$e->getMessage());
                //$this->clear();
            }
        } else {
            try{
                $this->_userData=$this->_uHandler->getUserByUsernameOrEmail($userid);
                //echo "UBEAN UID=".$this->_UBean->getc80_id()." ";
            } catch(\Exception $e){
                //echo "ERROR:".$e->getMessage();
                General::addRowLog("[Load User] exception".$e->getMessage());
                //$this->clear();
            }
        }
        if (isset($this->_userData["c80_id"]) && $this->_userData["c80_id"]>0){
            $this->mId     = (int)$this->_userData["c80_id"];
            $this->mUName  = $this->_userData["c80_username"];
            $this->mEmail  = $this->_userData["c80_email"];
            $this->mDBPass = $this->_userData["c80_password"];
            $this->mName   = $this->_userData["c80_name"];
            $this->mSurname= $this->_userData["c80_surname"];
            $this->mPsChgDt= $this->_userData["c80_pwd_chng"];
            return ($this->mId>0);
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
            $this->mDBPass=$this->_genPwd($this->mId, $this->mUName, $password);
            if ($this->mDBPass!=""){
                $data=[];
                $data['c80_password']=$this->mDBPass;
                if ($temporary)
                    $sca=date("Y/m/d H:i:s",strtotime("-1 week"));
                else
                    $sca=date("Y/m/d H:i:s",strtotime("+1 year"));
                $data['c80_pwd_chng']=$sca;
                $done=$this->_uHandler->updateUser($this->mId, $data);
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

    /**
     * Login user and store in session
     * Uses AuthManager to handle session storage
     *
     * @param string $userId Username or email
     * @param string $password User password
     * @return bool True if login successful, false otherwise
     */
    public static function login(string $userId, string $password): bool
    {
        return AuthManager::login($userId, $password);
    }

    /**
     * Login user with token and store in session
     * Uses AuthManager to handle session storage
     *
     * @param string $userId User ID
     * @param string $token Authentication token
     * @return bool True if login successful, false otherwise
     */
    public static function loginWithToken(string $userId, string $token): bool
    {
        return AuthManager::loginWithToken($userId, $token);
    }

    /**
     * Logout current user from session
     *
     * @return void
     */
    public static function logout(): void
    {
        AuthManager::logout();
    }

    /**
     * Check if user is currently authenticated
     *
     * @return bool True if user is authenticated, false otherwise
     */
    public static function isUserAuthenticated(): bool
    {
        return AuthManager::isAuthenticated();
    }

    /**
     * Get current authenticated user from session
     *
     * @return User|null User object if authenticated, null otherwise
     */
    public static function getCurrentUser(): ?User
    {
        return AuthManager::getUser();
    }

    /**
     * Get current authenticated user ID
     *
     * @return int User ID if authenticated, 0 otherwise
     */
    public static function getCurrentUserId(): int
    {
        return AuthManager::getUserId();
    }

    /**
     * Require authentication or die with error
     *
     * @param string $errorMessage Custom error message
     * @return void Dies if not authenticated
     */
    public static function requireAuthentication(string $errorMessage = "Authentication required"): void
    {
        AuthManager::requireAuth($errorMessage);
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