<?php

if (! function_exists('messaging_table')) {
    /**
     * Full table name for a messaging package logical table (with configured prefix).
     */
    function messaging_table(string $name): string
    {
        return config('messaging.table_prefix', 'messaging_').$name;
    }
}
