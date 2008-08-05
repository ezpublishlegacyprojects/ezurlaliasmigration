<?php
/**
 * File containing the ezpUrlAliasHistoryController class
 *
 * @copyright Copyright (C) 1999-2008 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2
 * @package UrlAliasMigration
 *
 */

include_once( 'lib/ezdb/classes/ezdb.php' );
include_once( 'kernel/classes/ezpersistentobject.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpurlaliasmigrationcontroller.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpurlaliasmigratetool.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpmigratedurlalias.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezurlaliasquerystrict.php' );

/**
 * Controller class for migrationg and restoring url history entries
 *
 * @package UrlAliasMigration
 */
class ezpUrlAliasHistoryController extends ezpUrlAliasMigrationController
{
    /**
     * Action for migrating all existing url history entries to the migration table.
     * 
     * @static
     * @return void
     */
    function migrateHistoryEntries()
    {
        $historyUrlCount = ezpUrlAliasMigrateTool::historyUrlCount();

        $migrateCount = 0;

        $fetchLimit = 50;
        $migrateOffset = 0;

        ezpUrlAliasMigrationController::setProgressCount( $historyUrlCount );

        $db =& eZDB::instance();

        while( $migrateCount < $historyUrlCount )
        {
            list( $historyUrlArray, $newOffset ) = ezpUrlAliasMigrateTool::historyUrl( $migrateOffset, $fetchLimit );

            foreach ( $historyUrlArray as $key => $entry )
            {
                $item =& $historyUrlArray[$key];
                $item['extra_data'] = ezpUrlAliasMigrateTool::extractUrlData( $item['parent'], $item['text_md5'], null );
            }

            $historyCopyArray = ezpUrlAliasQueryStrict::makeList( $historyUrlArray, true );

            foreach ( $historyCopyArray as $historyEntry )
            {
                $db->begin();
                $result = $historyEntry->store();
                ezpUrlAliasMigrationController::doCallback( $result );
                $db->commit();
            }

            $migrateCount += count( $historyUrlArray );
            $migrateOffset = $newOffset;

            unset( $historyUrlArray, $historyCopyArray );
        }
    }

    /**
     * Action for restoring migrated url history entries.
     * 
     * @static
     * @return void
     */
    function restoreHistoryEntries()
    {
        $cond = array();
        $cond = array( 'is_restored' => 0,
                       'is_original'=> 0 );

        $historyMigrateCount = eZPersistentObject::count( ezpMigratedUrlAlias::definition(), $cond );
        $restoreCount = 0;

        $fetchLimit = 50;
        $restoreOffset = 0;

        ezpUrlAliasMigrationController::setProgressCount( $historyMigrateCount );

        $db =& eZDB::instance();

        while ( $restoreCount < $historyMigrateCount )
        {
            list( $historyArray, $newOffset ) = ezpUrlAliasMigrateTool::migratedUrlAlias( $cond, 0, $fetchLimit );

            foreach ( $historyArray as $entry )
            {
                $db->begin();
                $result = $entry->analyse();
                ezpUrlAliasMigrationController::doCallback( $result );
                $db->commit();
            }

            $restoreCount += count( $historyArray );
            $restoreOffset = $newOffset;

            unset( $historyArray );
        }
    }
}
?>