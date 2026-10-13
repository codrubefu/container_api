<?php

namespace App\Database;

use Illuminate\Database\Schema\Grammars\SqlServerGrammar;

/**
 * The MasterERP server runs SQL Server 2014, which lacks `STRING_AGG` (added in 2017).
 * Index and foreign key introspection is rewritten with `FOR XML PATH` so schema
 * inspection (Scramble, `model:show`, `db:table`) works against it.
 */
class SqlServer2014SchemaGrammar extends SqlServerGrammar
{
    /**
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compileIndexes($schema, $table): string
    {
        return sprintf(
            'select idx.name as name, '
            .'stuff((select \',\' + col.name from sys.index_columns as idxcol '
            .'join sys.columns as col on idxcol.object_id = col.object_id and idxcol.column_id = col.column_id '
            .'where idxcol.object_id = idx.object_id and idxcol.index_id = idx.index_id '
            .'order by idxcol.key_ordinal for xml path(\'\'), type).value(\'.\', \'nvarchar(max)\'), 1, 1, \'\') as columns, '
            .'idx.type_desc as [type], idx.is_unique as [unique], idx.is_primary_key as [primary] '
            .'from sys.indexes as idx '
            .'join sys.tables as tbl on idx.object_id = tbl.object_id '
            .'join sys.schemas as scm on tbl.schema_id = scm.schema_id '
            .'where tbl.name = %s and scm.name = %s and idx.name is not null',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }

    /**
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compileForeignKeys($schema, $table): string
    {
        return sprintf(
            'select fk.name as name, '
            .'stuff((select \',\' + lc.name from sys.foreign_key_columns as fkc '
            .'join sys.columns as lc on fkc.parent_object_id = lc.object_id and fkc.parent_column_id = lc.column_id '
            .'where fkc.constraint_object_id = fk.object_id order by fkc.constraint_column_id '
            .'for xml path(\'\'), type).value(\'.\', \'nvarchar(max)\'), 1, 1, \'\') as columns, '
            .'fs.name as foreign_schema, ft.name as foreign_table, '
            .'stuff((select \',\' + fc.name from sys.foreign_key_columns as fkc '
            .'join sys.columns as fc on fkc.referenced_object_id = fc.object_id and fkc.referenced_column_id = fc.column_id '
            .'where fkc.constraint_object_id = fk.object_id order by fkc.constraint_column_id '
            .'for xml path(\'\'), type).value(\'.\', \'nvarchar(max)\'), 1, 1, \'\') as foreign_columns, '
            .'fk.update_referential_action_desc as on_update, '
            .'fk.delete_referential_action_desc as on_delete '
            .'from sys.foreign_keys as fk '
            .'join sys.tables as lt on lt.object_id = fk.parent_object_id '
            .'join sys.schemas as ls on lt.schema_id = ls.schema_id '
            .'join sys.tables as ft on ft.object_id = fk.referenced_object_id '
            .'join sys.schemas as fs on ft.schema_id = fs.schema_id '
            .'where lt.name = %s and ls.name = %s',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }
}
