<?php
/**
 * File containing the ezpUrlAliasController class
 *
 * @copyright Copyright (C) 1999-2008 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2
 * @package UrlAliasMigration
 *
 */

include_once( 'lib/ezdb/classes/ezdb.php' );
include_once( 'kernel/classes/ezpersistentobject.php' );
include_once( "lib/ezutils/classes/ezdebug.php" );
include_once( "lib/ezutils/classes/ezdebugsetting.php" );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpurlaliasmigrationcontroller.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpurlaliasmigratetool.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpmigratedurlalias.php' );
include_once( eZExtension::baseDirectory() . '/ezurlaliasmigration/classes/ezpurlaliasquerystrict.php' );

/**
 * ezpUrlAliasController implements the actions of the urlalias/mgirate and urlalias/restore view
 *
 * @package UrlAliasMigration
 */
class ezpUrlAliasController extends ezpUrlAliasMigrationController
{
    /**
     * Action migrating a selected set of custom url aliases to the migration table.
     *
     * @param array $elementList 
     * @static
     * @return void
     */
    function migrateAlias( $elementList )
    {
        foreach ( $elementList as $element )
        {
            if ( preg_match( "#^([0-9]+).([a-fA-F0-9]+).([a-zA-Z0-9-]+)$#", $element, $matches ) )
            {
                $parentID = (int)$matches[1];
                $textMD5  = $matches[2];
                $language = $matches[3];

                $db =& eZDB::instance();
                $rows = $db->arrayQuery( "SELECT * FROM ezurlalias_ml WHERE parent = {$parentID} AND text_md5 = '" . $db->escapeString( $textMD5 ) . "'" );
                $rows[0]['extra_data'] = ezpUrlAliasMigrateTool::extractUrlData( $parentID, $textMD5, $language );

                $migratedCopy = ezpUrlAliasQueryStrict::makeList( $rows, true );
                $migratedCopy = $migratedCopy[0];
                $db->begin();
                $migratedCopy->store();
                $db->commit();
            }
        }
    }

    /**
     * Action for migrating all custom url aliases to the migration table.
     * 
     * @static
     * @return void
     */
    function migrateAllAliases()
    {
        $urlCount = ezpUrlAliasMigrateTool::customUrlAliasCount();

        $migrateCount = 0;

        $fetchLimit = 50;
        $migrateOffset = 0;

        ezpUrlAliasMigrationController::setProgressCount( $urlCount );

        $db =& eZDB::instance();

        while( $migrateCount < $urlCount )
        {
            list( $aliasList, $newOffset ) = ezpUrlAliasMigrateTool::customUrlAlias( $migrateOffset, $fetchLimit );

            // Migrate the selected batch over

            // Future optimalisation possible, to merge the extract url data and
            // makeList() to get one loop instead of 2 over the rows.
            foreach ( $aliasList as $key => $entry )
            {
                $item =& $aliasList[$key];
                // Language not needed here anymore, the object will contain the correct lang-mask directly
                $item['extra_data'] = ezpUrlAliasMigrateTool::extractUrlData( $item['parent'], $item['text_md5'], null );
            }

            // Use the makeList() method to create array of ezpMigratedUrlAlias objects
            // this method is able to cope with corrupted lang_mask data
            // We use a flag $storageMode, to retain language information
            $objectArray = ezpUrlAliasQueryStrict::makeList( $aliasList, true );

            foreach ( $objectArray as $migratedAlias )
            {
                $db->begin();
                $result = $migratedAlias->store();
                ezpUrlAliasMigrationController::doCallback( $result );
                $db->commit();
            }

            // Prepare for next iteration
            $migrateCount += count( $aliasList );
            $migrateOffset = $newOffset;

            unset( $aliasList, $objectArray );
        }
    }

    /**
     * Action for restoring a selected set of migrated url alias elements.
     * 
     * The selected aliases for migrations are encoded in <var>$elementList</var>.
     * This is an array consisting of tuples in the form:
     * <pre>
     * [parentID].[textMD5]
     * </pre>
     *These two values are used to uniquely identify any entry in the url alias system.
     * 
     * @param array $elementList 
     * @static
     * @return void
     */
    function restoreAlias( $elementList )
    {
        foreach ( $elementList as $element )
        {
            if ( preg_match( "#^([0-9]+).([a-fA-F0-9]+)$#", $element, $matches ) )
            {
                $parentID = (int)$matches[1];
                $textMD5  = $matches[2];

                $c = array( "parent" => $parentID,
                            "text_md5" => $textMD5 );
                $selMigAliasList = ezpUrlAliasMigrateTool::migratedUrlAlias( $c );

                foreach( $selMigAliasList[0] as $migAlias )
                {
                    $result = $migAlias->analyse();
                    $output = $result ? "Restore ok" : "Restore not ok";
                    eZDebugSetting::writeDebug( "urlalias-migration-result", "Result: $output", __FUNCTION__ );
                }
            }
        }
    }

    /**
     * Action for restoring all migrated url aliases in the system.
     *
     * @static
     * @return void
     */
    function restoreAllAliases()
    {
        $cond = array( 'is_restored' => 0,
                       'is_original' => 1 );
        $count = eZPersistentObject::count( ezpMigratedUrlAlias::definition(), $cond );

        $restoreCount = 0;

        $fetchLimit = 50;
        $restoreOffset = 0;

        ezpUrlAliasMigrationController::setProgressCount( $count );

        $db =& eZDB::instance();

        while( $restoreCount < $count )
        {
            list( $aliasList, $newOffset ) = ezpUrlAliasMigrateTool::migratedUrlAlias( $cond, 0, $fetchLimit );

            // Restore the selected batch over
            foreach ( $aliasList as $alias )
            {
                $db->begin();
                $result = $alias->analyse();
                ezpUrlAliasMigrationController::doCallback( $result );
                $db->commit();
            }

            // Prepare for next iteration
            $restoreCount += count( $aliasList );
            $restoreOffset = $newOffset;

            unset( $aliasList );
        }
        $infoCode = "feedback-restore-all";
        $infoData['migrate-restore-count'] = $restoreCount;
    }

    /**
     * Action for removing a selected set of migrated url aliases from the system.
     * 
     * <var>$elementList</var> is an array of tuples, consisting of parent id,
     * separated by a dot and the text md5 value.
     *
     * @param array $elementList 
     * @static
     * @return void
     */
    function removeAlias( $elementList )
    {
        foreach ( $elementList as $element )
        {
            if ( preg_match( "#^([0-9]+).([a-fA-F0-9]+)$#", $element, $matches ) )
            {
                $parentID = (int)$matches[1];
                $textMD5  = $matches[2];

                $c = array( "parent" => $parentID,
                            "text_md5" => $textMD5 );
                $selMigAliasList = ezpUrlAliasMigrateTool::migratedUrlAlias( $c );

                $db =& eZDB::instance();
                $db->begin();
                foreach( $selMigAliasList[0] as $migAlias )
                {
                    $migAlias->remove();
                }
                $db->commit();
            }
        }
    }

    /**
     * Action for removing all migrated url aliases in the system.
     *
     * @static
     * @return void
     */
    function removeAllAliases()
    {
        $allMigratedUrls = ezpUrlAliasMigrateTool::migratedUrlAlias();

        $db =& eZDB::instance();
        $db->begin();
        foreach ( $allMigratedUrls[0] as $url )
        {
            $url->remove();
        }
        $db->commit();
    }

    /**
     * Action for inserting the table needed to hold migrated url alias entries.
     *
     * @static
     * @return void
     */
    function insertMissingTable()
    {
        $db =& eZDB::instance();
        $schemaFilePath = eZExtension::baseDirectory() . "/ezurlaliasmigration/sql/";
        $schemaFile = "schema.sql";

        $success = $db->insertFile( $schemaFilePath, $schemaFile );

        if ( !$success )
        {
            $errorMessage = $db->errorMessage() . ":" . $db->errorNumber();
            eZDebug::writeError( $errorMessage, __CLASS__ . ':' . __FUNCTION__ );
        }
    }
}
?>