<?php

use Context\IfStmtContext;
use Context\ElseIfStmtContext;

trait IfHandler
{
    // if condicion { } else { } 
    public function visitIfStmt($ctx): mixed
    {
        $condition = $this->visit($ctx->e());

        

        if (!is_bool($condition)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => 'La condición del if debe ser de tipo bool',
                'line' => $ctx->e()->getStart()->getLine(),
                'col'  => $ctx->e()->getStart()->getCharPositionInLine(),
            ];
            return null;
        }

        if ($condition) {
            $this->visit($ctx->block(0));
        } elseif ($ctx->block(1) !== null) {
            $this->visit($ctx->block(1));
        }

        return null;
    }

    // if condicion { } else if condicion { } 
    public function visitIfElseIfStmt($ctx): mixed
    {
        $condition = $this->visit($ctx->e());

        if (!is_bool($condition)) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => 'La condición del if debe ser de tipo bool',
                'line' => $ctx->e()->getStart()->getLine(),
                'col'  => $ctx->e()->getStart()->getCharPositionInLine(),
            ];
            return null;
        }

        if ($condition) {
            $this->visit($ctx->block());
        } else {
            $this->visit($ctx->stmt());
        }

        return null;
    }
}