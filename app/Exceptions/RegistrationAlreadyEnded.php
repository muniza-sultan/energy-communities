<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Internal signal inside TransitionRegistration::end(): the registration was
 * already in a terminal state. Never leaves the action.
 */
class RegistrationAlreadyEnded extends RuntimeException {}
