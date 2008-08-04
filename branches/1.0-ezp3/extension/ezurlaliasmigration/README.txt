eZ Publish url migration readme
===============================

The url alias migration extension is meant to help with retaining run-time
data in the system's url alias table, which cannot automatically be
regenerated after a reset of this table.

This extension will allow you to migrate two types of url aliases, custom url
aliases, and url history elements. The first type are entries which are
manually entered by the user, the latter are automatically generated entries,
which are created when you a user renames objects.

The migration operation is meant to be run in a specific workflow. The steps of
this workflow are as following:

1. Backup current database (at least ezurlalias_ml table)
2. Migrate existing data over to the migration table (see notes below)
3. Truncate the existing ezurlalias_ml table
4. Regenerate ezurlalias_ml data, with bin/php/updateniceurls.php

   If you have been running a site upgraded from 3.9.x or earlier, running on
   4.0.0 or 3.10.0 then it is best to not use the import-* options of this
   script as that will bring back all the old history redirects from the old
   system, which might be different in the current setup.
5. Directly after doing step 4) the migration restore process should be run to
   re-insert the data into the real ezurlalias_ml table.

   If this step is postponed, the results may be mixed, the extension expects
   to be run right after regeneration of this table. The important caveats are
   that settings which have an effect on url alias generation must not change
   between migration and restoration. Examples of such settings are:

    site.ini.[URLTranslator].TransformationGroup=...

    site.ini.[SiteAccessSettings].PathPrefix=...

The original intention of this extension was to provide users of eZ Publish
4.0.0 and 3.10.0 a method of retaining manually entered url aliases and url
history. When upgrading to 4.0.1 and 3.10.1. This upgrade step requires the
ezurlalias_ml table to be truncated and rebuilt.

Difference between GUI and CLI version
---------------------------------------

The script provides two different access points. There is a command-line
interface, and a graphical user interface in the administration interface.

Command-line interface
~~~~~~~~~~~~~~~~~~~~~~

The cli-script is useful for large scale migrations, where you need to migrate
and restore a large number of url alias- and history- entries. The cli-script
allows you to migrate and restore both custom url aliases and history entries.

Graphical user interface
~~~~~~~~~~~~~~~~~~~~~~~~

The graphical user interface is available under the "Url Alias" tab in the
administration interface. This version of the interface only works with custom
url aliases for now, in line with the rest of the gui, which does not expose
the url history entries.

In this GUI, you will be able to see all defined custom url aliases, in one
place, regardless of where they are defined, and you can select to migrate and
restore such aliases. Users should be aware that as a web-interface, this
method is sensitive to timeouts which can occur on web-pages, for long
operations, if you have a large number of aliases, the cli-version should be
used instead.

Using the GUI-version makes it easy to debug a smaller set of url aliases,
wanted to be migrated as well, by enabling the wanted debug.ini.append.php
entries in the extension, more debug output will be provided.
