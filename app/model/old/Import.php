<?php

namespace App\Model;

/**
 * Importing class
 */
class Import {
    
    public function readPgBackup($file, $tmponly, $tables, $columns) {
        $f = fopen($file, "r");
        if ($tmponly) {
            $temporary = "TEMPORARY";
        } else {
            $temporary = "";
        }
        $tabregexp = join("|", array_keys($tables));
        $processed=0;
        $skipped=0;
        while ($line = fgets($f)) {
            if (preg_match("/CREATE TABLE\s/", $line)) {
                self::dbg("Import: reading $line");
            }
            if (preg_match("/CREATE TABLE\s($tabregexp)\s/", $line, $regs)) {
                $itable = $regs[1];
                $otable = $tables[$itable];
                $csql = "--- $line";
                $csql.="CREATE $temporary TABLE IF NOT EXISTS $otable (\n";
                while ($line = fgets($f)) {
                    $csql.=$line;
                    if (preg_match("/^\)/", $line)) {
                        echo $csql;
                        break;
                    }
                }
            }
            if (preg_match("/COPY\s/", $line)) {
                self::dbg("Import: reading $line");
            }
            if (preg_match("/COPY\s($tabregexp)\s/", $line, $regs)) {
                $itable = $regs[1];
                $otable = $tables[$itable];
                echo "--- $line";
                echo str_replace($itable, $otable, $line);
                if (array_key_exists($itable, $columns)) {
                    $filter = true;
                } else {
                    $filter = false;
                }
                while ($line = fgets($f)) {
                    $processed++;
                    if (preg_match('#^\.$#', $line)) {
                        echo '\.'."\n";
                        break;
                    }
                    if ($filter && self::filterline($line, $columns[$itable])) {
                        $skipped++;
                        continue;
                    }
                    echo $line;
                    if ($processed % 10000==0) {
                        self::dbg("Import($itable>$otable): processed $processed lines (skipped $skipped)      \r");
                    }
                }
            }
        }
        fclose($f);
    }
    
    public function dbg($msg) {
        fprintf(STDERR,$msg);
    }

    public function filterline($line, $columns) {
        $cols = preg_split("/\t/", $line);
        foreach ($columns as $i=>$c) {
            if (array_key_exists("min",$c)) {
                return ($cols[$i]<$c["min"]);
            }
            if (array_key_exists("max",$c)) {
                return ($cols[$i]>$c["max"]);
            }
        }
        return (false);
    }

}
