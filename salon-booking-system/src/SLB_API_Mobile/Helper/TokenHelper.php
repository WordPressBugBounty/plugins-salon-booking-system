<?php

namespace SLB_API_Mobile\Helper;

/**
 * Same credential store as the desktop API (one token per user).
 * Implementation lives in SLB_API\Helper\TokenHelper so the two clients cannot drift.
 */
class TokenHelper extends \SLB_API\Helper\TokenHelper {
}
