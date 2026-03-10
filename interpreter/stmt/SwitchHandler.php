<?php

trait SwitchHandler
{
    public function visitSwitchStmt($ctx): mixed
    {

        $tieneExpr = $ctx->e() !== null;
        // Evaluar la expresión del switch
        $valor     = $tieneExpr ? $this->visit($ctx->e()) : true;
        $executed = false; // ya se ejecutó un case
        $defaultCase = null;
        

            foreach ($ctx->switchCase() as $case) {
            $esDefault = ($case instanceof Context\DefaultStmtContext);

            if ($esDefault) {
                // Guarda el default para después
                $defaultCase = $case;
            } else {
                // case normal
                $caseVal = $this->visit($case->e());
                $match   = $tieneExpr ? ($valor == $caseVal) : ($caseVal === true);

                if ($match && !$executed) {
                    $executed = true;
                    foreach ($case->stmt() as $stmt) {
                        $this->visit($stmt);
                        if ($this->breaking || $this->returning) break;
                    }
                    $this->breaking = false;
                    break;
                }
            }
        }

        // Si ningún case ejecutó y hay default, ejecutarlo ahora
        if (!$executed && $defaultCase !== null) {
            foreach ($defaultCase->stmt() as $stmt) {
                $this->visit($stmt);
                if ($this->breaking || $this->returning) break;
            }
            $this->breaking = false;
        }

        return null;
    }
    
    public function visitCaseStmt($ctx): mixed
    {
        // Manejo directo en visitSwitchStmt
        return null;
    }

    public function visitDefaultStmt($ctx): mixed
    {
        // Manejo directo en visitSwitchStmt
        return null;
    }

}