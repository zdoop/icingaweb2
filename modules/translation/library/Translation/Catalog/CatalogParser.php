<?php
/* Icinga Web 2 | (c) 2016 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Translation\Catalog;


/**
 * Class CatalogParser
 *
 * Reads gettext PO files and outputs the contained entries.
 *
 * @package Icinga\Module\Translation\Catalog
 */
class CatalogParser
{
    /**
     * Create a new CatalogParser
     *
     * @param   string  $catalogPath    The path to the catalog file to parse
     */
    public function __construct($catalogPath)
    {

    }

    /**
     * Parse the given catalog file and return its entries
     *
     * @param   string  $catalogPath    The path to the catalog file to parse
     */
    public static function parsePath($catalogPath)
    {
        $parser = new static($catalogPath);
        return $parser->parse();
    }

    /**
     * Parse the catalog file and return its entries
     *
     * @return  array
     */
    public function parse()
    {

    }
}
