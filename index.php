<?php

require_once "vendor/autoload.php";

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
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


//middleware to handle CSRF
//$app->add(new csrf\Guard);

/**
 * [App-wide middleware to handle XHR]
 * @param  {[Psr\Http\Message\ServerRequestInterface]} $req  [PSR Request Interface object]
 * @param  {[Psr\Http\Message\ResponseInterface]} $res  [PSR Response Interface object]
 * @param  {[type]} $next [Next ]
 * @return {[type]}       [description]
 */
$app->add(function($req, $res, $next){
    $response = $next($req, $res);
    if (!$req->isXhr()) {
        return $response->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, userid')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    }
});


/**
 * [Handle '/' GET request]
 * @param  {[Request]} Request  [Request object]
 * @param  {[Response]} Response [Response object]
 * @return {[Response]}          [Returned response to requestor]
 */
$app->get('/', function (Request $req, Response $res){
    $res->getBody()->write("SafePal API v1.5");
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
            $user = $this->auth->CheckAuth($username, $hash, $eventTime);
            if (sizeof($user) > 0) {
              $isLogged = $this->auth->LogAccess($username, 'login');
            }
            return (sizeof($user) > 0) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), "user" => $user)) : $res->withJson(array(getenv('STATUS')  => getenv('FAILURE_STATUS'), getenv('MSG') => "Login failed!"));
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

            return ($result['caseNumber']) ? $res->withJson(array(getenv('STATUS')  => getenv('SUCCESS_STATUS'), getenv('MSG') => "Report added successfully!", "casenumber" => $result['caseNumber'], "csos" => $result['csos'])) : $res->withJson(array(getenv('STATUS') => getenv('FAILURE_STATUS'), getenv('MSG') => "Failed to add report"));

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

    });
})->add(new pal\AuthMiddleware());

/**
 * Run SLIM app
 */
$app->run();

?>
