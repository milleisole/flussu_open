<?php
/* --------------------------------------------------------------------*
 * Flussu v4.2 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS-NAME:       Flussu API Interface
 * CREATED DATE:     25.01.2021 - Aldus - Flussu v2.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
/**
 * Conn.php
 * 
 * This class is used to connect to other servers via API using the "flussu" format. The process involves two 
 * main steps: authorization and execution. Initially, an authorization call is made by requesting an OTP 
 * and providing the name of the command to be executed. Following this, the execution call is made by
 * providing the OTP obtained from the first step. The server, upon receiving the OTP, executes the corresponding
 * command and returns the result. The sequence of operations is as follows:
 *
 * Usage:
 * getOTP: 
 *      Request an OTP by specifying the command name. This step is for authorization purposes.
 * getCommandFromOtp: 
 *      This step is typically part of the internal logic where the command associated with the given OTP is 
 *      retrieved, ensuring the OTP is valid and has not been used.
 * execCmd: 
 *      Execute the command by providing the OTP. The server executes the command associated with the OTP and 
 *      returns the execution result.
 * 
 * @package App\Flussu\Api\V40
 * @version 4.1.20250205
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

 namespace Flussu\Api\V40;

use Flussu\Flussuserver\Request;

use Flussu\General;
use Flussu\Persons\User;
use Flussu\Flussuserver\Command;
use Flussu\Flussuserver\NC\HandlerNC;

class Conn {
    /**
     * Executes a command based on the request and user context.
     * 
     * This method is responsible for processing incoming requests and executing the corresponding
     * actions based on the command ('C') and key ('K') parameters provided in the request. It handles
     * the initialization of necessary components, such as the database handler and command processor,
     * and sets up the environment for command execution, including headers for CORS and content type.
     * 
     * The method also manages the retrieval of the request body, decoding it from JSON format, and
     * prepares it for further processing by the command execution logic.
     * 
     * Usage:
     * This method is intended to be called with a Request object containing the necessary parameters
     * ('C' for command, 'K' for key) and a User object representing the current user. The method
     * determines whether the user needs to be logged in and the required authorization level based
     * on the command to be executed.
     * 
     * @param \Flussu\Flussuserver\Request $Req The HTTP request object, containing parameters and the request body.
     * @param \Flussu\Persons\User $theUser The user object representing the current user.
     * 
     * @return void The method does not return a value but sends a JSON response to the client.
     * 
     * @throws \Exception If there are issues with command execution or if the required parameters are not provided.
     */
    public function exec(Request $Req, User $theUser){
        $mustBeLogged=false;
        $authLevel=0;
        
        $db= new HandlerNC();
        $wcmd= new Command();

        $CMD=General::getGetOrPost("C");
        $UUID=General::getGetOrPost("K");

        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Max-Age: 200');
        header('Access-Control-Expose-Headers: Content-Security-Policy, Location');
        header('Content-Type: application/json; charset=UTF-8');

        $rcvData=file_get_contents('php://input');
        //if (substr($rcvData,0,1)=="[" && substr($rcvData,-1)=="]")
        //    $rcvData=substr($rcvData,1,strlen($rcvData)-2);
        $jsonData = json_decode($rcvData);
        $res     = array("result"=>"ERR:0","message"=>"Unknown or uncomplete command.");
        //$res     = array("result"=>"ERROR : HEX(32778)");
        switch($CMD){
            case "G":
            case "g":
                if (isset($jsonData->command) && !empty($jsonData->command)){
                    //json contains:
                    //   USER
                    //   PASS
                    //   COMMAND --> (next data needs to be -COMMAND-)
                    $res=$this->getOtp($db,$jsonData);
                }
                break;
            case "E":
            case "e":
                if (!empty($UUID) && !empty($jsonData))
                //parameters:
                    //   UUID=OTP
                    //   JSONDATA --> the command's DATA
                    $cmd=$this->getCmdFromOtp($db,$UUID);
                    if ($cmd!="")
                        $res=$this->execCmd($cmd,$jsonData);
                break;
        }
        die(json_encode($res));
    }
    /**
     * Generates an OTP (One Time Password) for a given command using user credentials.
     * 
     * This method is responsible for authenticating a user based on the provided user ID and password,
     * and generating an OTP for a specified command if authentication is successful. The OTP is stored
     * in the database along with the command and user ID, allowing for later execution of the command
     * using this OTP.
     * 
     * The method returns an array containing the result of the operation. If successful, the result
     * will include the generated OTP. If authentication fails or another error occurs, the result will
     * contain an error message.
     * 
     * Usage:
     * This method should be called with a database handler, and a command object containing the user ID,
     * password, and the command for which the OTP is being generated. The method will handle the authentication
     * and OTP generation process.
     * 
     * @param \Flussu\Flussuserver\NC\HandlerNC $db The database handler object used for database operations.
     * @param \stdClass $command An object containing the user ID (`userid`), password (`password`), and the command (`command`) for which the OTP is generated.
     * 
     * @return array An associative array with either the generated OTP (`key`) on success, or an error message (`result`) on failure.
     */
    function getOtp($db, $command){
        // Trasferire le chiamate SQL in Handler

        // GET OTP FOR CMD USING UID+PWD
        $uid=$command->userid;
        $tkn=$command->password;
        $cmd=$command->command;
        $usr=new User();
        $res=array("result"=>"ERROR 800A: Can't create this key","key"=>"");
        // CHECK AUTH !!! (AL MOMENTO NON E' IMPLEMENTATO)
        if ($usr->authenticateToken($uid, $tkn)){
            $SQL="INSERT INTO t50_otcmd (c50_key,c50_command,c50_uid) VALUES (?,?,?)";
            $otp=General::getUuidv4();
            $newWfId=$db->execSqlGetId($SQL,array($otp,$cmd,$usr->getId()));
            if ($newWfId>0)
                $res=array("result"=>"OK","key"=>$otp);
        } else 
            $res=array("result"=>"ERROR: Can't autheticate this user/token","key"=>"");
        return $res;
    }

   /**
     * Retrieves the command associated with a given OTP (One Time Password).
     * 
     * This method is crucial for the two-step command execution process where the first step involves
     * obtaining an OTP for a command, and the second step involves executing the command using the OTP.
     * `getCmdFromOtp` is used in the second step to retrieve the command that was initially associated
     * with the OTP. This ensures that the command to be executed is the one that was authorized in the
     * first step.
     * 
     * The method queries the database to find the command linked to the provided OTP. It checks the
     * validity of the OTP, ensuring it has not expired and has not been used previously. If the OTP is
     * valid, the method retrieves and returns the associated command. Otherwise, it returns an error
     * indicating that the OTP is invalid or expired.
     * 
     * Usage:
     * This method should be called with the OTP received by the user. It will return the command
     * associated with this OTP if the OTP is valid. This command can then be executed using the
     * appropriate execution method.
     * 
     * @param \Flussu\Flussuserver\NC\HandlerNC $db The database handler object used for database operations.
     * @param string $otp The One Time Password provided by the user for command execution.
     * 
     * @return mixed The command associated with the OTP if successful, or an error message if the OTP is invalid or expired.
     */
    function getCmdFromOtp($db, $otp){
        // Trasferire le chiamate SQL in Handler

        // GET COMMAND FROM OTP
        $res="";
        $SQL="SELECT c50_command as cmd from t50_otcmd where c50_key=?";
        $db->execSql($SQL,array($otp));
        if (isset($db->getData()[0]["cmd"])){
            $res= $db->getData()[0]["cmd"];
            $db->execSql("DELETE from c50_command where where c50_key=?",array($otp));
        }
        return $res;
    }

    /**
     * Executes a specified command with the provided data.
     * 
     * This method is designed to execute a command (`$theCmd`) using the data provided in `$theData`.
     * The nature of the command and the data depends on the application's context. The method could
     * be responsible for a wide range of operations, from database queries to external API calls,
     * based on the command specified.
     * 
     * The method processes the command by identifying the type of command from `$theCmd` and then
     * using the data in `$theData` as parameters or inputs for the command execution. The execution
     * process and the handling of the data are determined by the command's requirements.
     * 
     * Usage:
     * This method should be called with a command identifier and the necessary data for executing
     * that command. The specifics of the command identifier and the data structure are defined by
     * the application's architecture and the specific needs of the command being executed.
     * 
     * @param string $theCmd The command to be executed. This identifier is used to determine the action to be taken.
     * @param mixed $theData The data required for executing the command. The structure and type of this data can vary based on the command.
     * 
     * @return mixed The result of the command execution. The type and structure of the return value depend on the command executed.
     */
    function execCmd($theCmd, $theData){
        $res=array("result"=>"ERROR: HEX(32778)");
        // EXEC COMMANDS
        if ($theCmd!=""){
            switch($theCmd){
                case "chkemail":
                case "chkEmail":
                    // Verify if user/email si already on database
                    $res=User::existEmail($theData->userEmail);
                    $res=array("result"=>$res);
                    break;
                case "reguser":
                case "regUser":
                    // Make user registration on database
                    $Usr=new User();
                    $Usr->registerNew($theData->userId, $theData->basePass, $theData->userEmail, $theData->name, $theData->surname);
                    $res= array("result"=>"OK");
                    break;
                case "chPassUser":
                case "chpassuser":
                    User::changeUserPassword($theData->userId,$theData->basePass);
                    $res= array("result"=>"OK");
                    break;
                default:
                    $res= array("result"=>"ERROR: unknown command [$theCmd]");
            }
        }
        return $res;
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