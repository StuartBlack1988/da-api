<?php

use Slim\Routing\RouteCollectorProxy;
use DietitianAssist\Practice\PracticeController;

return function (RouteCollectorProxy $group) {
    $group->post('/register', [PracticeController::class, 'registerPractice']);
}; 