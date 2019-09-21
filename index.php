<?php
//header("Access-Control-Allow-Origin: *");
// header('Content-type:application/json');
require_once "vendor/autoload.php";

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
// use Fastroute\Dispatcher;
// use Tuupola\Middleware\CorsMiddleware;
use Slim\Csrf as csrf;

//SafePal
use SafePal as pal;


//monolog
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * [$dotenv load env variables]
 * @type {Dotenv}
 */
$dotenv = new Dotenv\Dotenv(__DIR__, '.env');
$dotenv->load();

/**
 * [$slimConfig slim config]
 * @type {Array}
 */
$slimConfig = require_once getenv('PATH_TO_CONFIG')."slim.php";

$app = new \Slim\App($slimConfig);
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});



/**
 * [$dicontainer get dependency container]
 * @type {object}
 */
$dicontainer = $app->getContainer();

/**
 * [SafePalAuth Dependency Injection]
 * @param  {[SafePalAuth]} $d [SafePalAuth]
 * @return {SafePalAuth}    [Handles all authentication-related work]
 */
$dicontainer['auth'] = function ($d){
    $auth = new pal\SafePalAuth;
    return $auth;
};

/**
 * [SafePalReport Dependency Injection]
 * @param  {[SafePalReport]} $rp [SafePalReport]
 * @return {[SafePalReport]}     [Handles all reports/cases-related work]
 */
$dicontainer['reports'] = function ($rp){
    return new pal\SafePalReport();
};

/**
 * [SafePalReport Dependency Injection]
 * @param  {[SafealCSOs]} $rp [SafePalReport]
 * @return {[SafealCSOs]}     [Handles all cso/cases-related work]
 */
$dicontainer['csos'] = function ($rp){
    return new pal\SafePalCSO();
};


//middleware to handle CSRF
//$app->add(new csrf\Guard);

/**
 * [App-wide middleware to handle XHR]
 * @param  {[Psr\Http\Message\ServerRequestInterface]} $req  [PSR Request Interface object]
 * @param  {[Psr\Http\Message\ResponseInterface]} $res  [PSR Response Interface object]
 * @param  {[type]} $next [Next ]
 * @return {[type]}       [description]
 */




/**
 * [Handle '/' GET request]
 * @param  {[Request]} Request  [Request object]
 * @param  {[Response]} Response [Response object]
 * @return {[Response]}          [Returned response to requestor]
 */
// $app->options('/*', function (Request $req, Response $res){
//     return $response->withHeader('Access-Control-Allow-Origin', '*')
//         ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, userid')
//             ->withHeader('Access-Control-Allow-Methods', 'GET, POST, ,PUT, OPTIONS, DELETE');
// });
$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('headers.allow',['Accept', 'Content-Type'])
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, userid')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

 $app->get('/', function (Request $req, Response $res){
    $res->getBody()->write("SafePal API v1.6");
    return $res;
});


/**
 * [Handle route - /api/v1]
 * @return {[Response]} [Return appropriate responses for each request group]
 */
$app->group('/api/v1', function () use ($app) {

    /**
     * [Handle - /api/v1/auth routes]
     * @return {[Response]} [Server response]
     */
    $app->group('/auth', function () use ($app){

      /**
       * [Handle 'auth/newtoken' GET requests]
       * @param  {[Request]} Request  [Request object]
       * @param  {[Response]} Response [Response object]
       * @return {[Response]}          [Returned response to requestor]
       */
        $app->get('/newtoken', function (Request $req, Response $res) use ($app){

            $user = $req->getHeaderLine('userid');

            if (!empty($user)) {

                $auth = $this->get('auth');

                if (!$auth->ValidateUser($user)) {
                    return $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => getenv('INVALID_USER_MSG')));
                }

                $token = $auth->GetToken($user);

                return $res->withJson(array(getenv('STATUS') => getenv('SUCCESS_STATUS'), "token" => $token));
            }
        });

    


        /**
         * [Handle 'auth/checktoken' POST requests -- no body implementation. already handled through middleware]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
        $app->post('/checktoken', function (Request $req, Response $res) use ($app){
        });


        /**
         * [Handle 'auth/login' POST requests]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
        $app->post('/login', function (Request $req, Response $res) use ($app){

            $username = $req->getParsedBody()['username'];
            $hash = $req->getParsedBody()['hash'];
            $user = $this->auth->CheckAuth($username, $hash);
            //error_log(print_r($user, true));
            if (sizeof($user) > 0) {
              $isLogged = $this->auth->LogAccess($username, 'login');
            }
            return (sizeof($user) > 0) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), "user" => $user)) : $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), getenv('MSG') => "Login failed!"));
        });

        $app->post('/register', function(Request $req, Response $res) use ($app){
  
            $auth = $req->getParsedBody();

            //add report
            $result = $this->auth->RegisterAuth($auth);
             // print_r($result);
            return ($result) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), getenv('MSG') => "Auth added successfully!")) : $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to add auth"));

        });


        /**
         * [Handle 'auth/logout' POST requests]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
        $app->post('/logout', function (Request $req, Response $res) use ($app){

            $username = $req->getParsedBody()['username'];
            $isLogged = $this->auth->LogAccess($username, 'logout');
            return ($isLogged) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'))) : $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), getenv('MSG') => "Logging failed!"));
        });

    });

    /**
     * [Handle - /api/v1/reports routes]
     * @return {[Response]} [Server response]
     */
    $app->group('/reports', function() use ($app) {

      /**
       * [Handle 'reports/addreport' POST requests]
       * @param  {[Request]} Request  [Request object]
       * @param  {[Response]} Response [Response object]
       * @return {[Response]}          [Returned response to requestor]
       */
        $app->post('/addreport', function(Request $req, Response $res) use ($app){

            $report = $req->getParsedBody();

            //add report
            $result = $this->reports->AddReport($report);
            // if ($result['caseNumber']){ 
            // echo json_encode(array(
            //     getenv('STATUS')  => getenv('SUCCESS_STATUS'),
            //     getenv('MSG') => "Report added successfully!",
            //     "casenumber" => $result['caseNumber'], 
            //     "csos" => $result['csos']
            // ));
            // exit();
            // }
            // else{
            //     echo json_encode(array(
            //         getenv('STATUS')  => getenv('FAILURE_STATUS'),
            //         getenv('MSG') => "Failed to add report"
            //     ));
            //     exit();
            // }
            return ($result['caseNumber']) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), 
                                                                    getenv('MSG') => "Report added successfully!", 
                                                                    "casenumber" => $result['caseNumber'], 
                                                                    "csos" => $result['csos']
                                                                )
                                                            ) : $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to add report"));

        });

        /**
         * [Handle 'reports/all' POST requests]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
        $app->post('/all', function (Request $req, Response $res) use ($app){

            $csoID = 0;
            if (!empty($req->getParsedBody()['cso_id'])) {
                $csoID = $req->getParsedBody()['cso_id'];
            }

            $allreports = $this->reports->GetAllReports($csoID);

            return (sizeof($allreports) > 0) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), "reports" => $allreports["all"], "user_reports" => $allreports["user"])): $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), "reports" => array()));

        });

        /**
         * [Handle 'reports/addcontact' POST requests]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
        $app->post('/addcontact', function (Request $req, Response $res) use ($app){

            $report = $req->getParsedBody();

            $update = $this->reports->AddContact($report['caseNumber'], $report['contact']);

            return ($update) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), getenv('MSG') => "Contact added successfully!")): $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to update contact!"));

        });
});


/**
     * [Handle - /api/v1/csos routes]
     * @return {[Response]} [Server response]
     */
    $app->group('/cso', function() use ($app) {

        /**
         * [Handle 'cso/add' POST requests]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
          $app->post('/add', function(Request $req, Response $res) use ($app){
  
              $report = $req->getParsedBody();
  
              //add report
              $result = $this->csos->AddCso($report);
               // print_r($result);
              return ($result) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), getenv('MSG') => "CSO added successfully!")) : $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to add report"));
  
          });

          $app->put('/', function(Request $req, Response $res) use ($app){
  
            $report = $req->getParsedBody();

            //add report
            $result = $this->csos->updateCso($report);
             // print_r($result);
            return ($result) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), getenv('MSG') => "CSO updated successfully!")) : $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to updated report"));

        });
  
          /**
           * [Handle 'csos/all' POST requests]
           * @param  {[Request]} Request  [Request object]
           * @param  {[Response]} Response [Response object]
           * @return {[Response]}          [Returned response to requestor]
           */
          $app->post('/all', function (Request $req, Response $res) use ($app){
  
            //   $csoID = 0;
            //   if (!empty($req->getParsedBody()['cso_id'])) {
            //       $csoID = $req->getParsedBody()['cso_id'];
            //   }
  
              $allcsos = $this->csos->GetAllCSOs();
  
              return (sizeof($allcsos) > 0) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), "csos" => $allcsos)): $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), "csos" => array()));
  
          });
          $app->post('/', function (Request $req, Response $res) use ($app){
  
              $csoID = null;
              if (!empty($req->getParsedBody()['cso_id'])) {
                  $csoID = $req->getParsedBody()['cso_id'];
              }
  
              $cso = $this->csos->GetCSO($csoID);

              echo json_encode(array(
                'status' => 'success',
                'data' => $cso[0]
            ));
            exit();
  
           //   return $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), "cso" => $cso)));
  
          });
  
          /**
           * [Handle 'reports/addcontact' POST requests]
           * @param  {[Request]} Request  [Request object]
           * @param  {[Response]} Response [Response object]
           * @return {[Response]}          [Returned response to requestor]
           */
        //   $app->post('/addcontact', function (Request $req, Response $res) use ($app){
  
        //       $report = $req->getParsedBody();
  
        //       $update = $this->reports->AddContact($report['caseNumber'], $report['contact']);
  
        //       return ($update) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), getenv('MSG') => "Contact added successfully!")): $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to update contact!"));
  
        //   });
  });

    /**
     * [Handle - /api/v1/activity routes]
     * @return {[Response]} [Server response]
     */
    $app->group('/activity', function () use ($app){

      /**
       * [Handle 'activity/addactivity' POST requests]
       * @param  {[Request]} Request  [Request object]
       * @param  {[Response]} Response [Response object]
       * @return {[Response]}          [Returned response to requestor]
       */
        $app->post('/addactivity', function (Request $req, Response $res) use ($app){

            $note = $req->getParsedBody();

            if (empty($note)) {
                $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), getenv('MSG') => getenv('NOTE_EMPTY_MSG')));
            }

            $result = $this->reports->AddNote($note);

            return ($result) ? $res->withJson(array(getenv('STATUS') => getenv('SUCCESS_STATUS'), getenv('MSG') => getenv('NOTE_SUCCESS_MSG'))): $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => getenv('NOTE_FAILURE_MSG')));

        });

        /**
         * [Handle 'activity/all' POST requests]
         * @param  {[Request]} Request  [Request object]
         * @param  {[Response]} Response [Response object]
         * @return {[Response]}          [Returned response to requestor]
         */
        $app->post('/all', function (Request $req, Response $res) use ($app){

             $allnotes = $this->reports->GetAllNotes();

             return (sizeof($allnotes) > 0) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), "notes" => $allnotes)): $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), "notes" => array()));

        });
        
        $app->map(['OPTIONS', 'GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
            $handler = $this->notFoundHandler; // handle using the default Slim page not found handler
            return $handler($req, $res);
        });

    });
})->add(new pal\AuthMiddleware());


/**
 * Run SLIM app
 */
$app->run();

?>
