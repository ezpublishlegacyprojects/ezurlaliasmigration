<?php
/**
 * File containing the ezpUrlAliasMigrationController class
 *
 * @copyright Copyright (C) 1999-2008 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2
 * @package UrlAliasMigration
 *
 */

/**
 * Super class for controllers used in the url alias migration extension.
 * 
 * This class simply provide shared functionality between the controllers, such as
 * script progress output.
 *
 * @package UrlAliasMigration
 */
class ezpUrlAliasMigrationController
{
    /**
     * Set callback function which can be used to get progess report from the controller
     * classes when doing migration oeprations.
     *
     * @param callback $callback
     * @static
     * @return void
     */
    function setProgressCallback( $callback )
    {
        $callbackMethod =& $GLOBALS['ezpUrlAliasMigrationController-callbackMethod'];
        $callbackMethod = $callback;
    }

    /**
     * Set the number of iteration for the current operation to $count.
     * 
     * If no callback method have been defined for the class, e.g. the controller is
     * triggered from web gui, no call will be made to eZScript.
     * 
     * @param int $count
     * @static
     * @return void
     */
    function setProgressCount( $count )
    {
        $callback =& $GLOBALS['ezpUrlAliasMigrationController-callbackMethod'];
        if ( $callback !== null )
        {
            $script =& eZScript::instance();
            $script->resetIteration( $count );
        }
    }

    /**
     * Triggers the callback function is one has been defined, if not, no action will
     * be performed.
     *
     * @param boolean $result 
     * @static
     * @return void
     */
    function doCallback( $result )
    {
        $callback =& $GLOBALS['ezpUrlAliasMigrationController-callbackMethod'];
        if ( $callback !== null )
        {
            call_user_func( $callback, $result );
        }
    }
}
?>