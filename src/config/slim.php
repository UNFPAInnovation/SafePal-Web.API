<?php

return [
  'settings'=> [
    'displayErrorDetails' => getenv('DISPLAYERRORDETAILS'),
    'debug' => getenv('DISPLAYERRORDETAILS'),
    "determineRouteBeforeAppMiddleware" => true
    ]
];

?>
