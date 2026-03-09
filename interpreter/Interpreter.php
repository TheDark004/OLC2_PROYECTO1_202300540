<?php

require_once __DIR__ . '/../interpreter/Environment.php';
require_once __DIR__ . '/../interpreter/expr/ExprHandler.php';
require_once __DIR__ . '/../interpreter/stmt/PrintHandler.php';
require_once __DIR__ . '/../interpreter/stmt/VarHandler.php';
require_once __DIR__ . '/../interpreter/stmt/IfHandler.php';
require_once __DIR__ . '/../interpreter/stmt/ForHandler.php';
require_once __DIR__ . '/../interpreter/stmt/FuncHandler.php';

class Interpreter extends GolampiBaseVisitor
{
    use ExprHandler, PrintHandler, VarHandler, IfHandler, ForHandler, FuncHandler;

    public string $console     = '';
    public array  $errors      = [];
    public bool   $breaking    = false;
    public bool   $continuing  = false;
    public bool   $returning   = false;
    public mixed  $returnValue = null;
    public Environment $env;

    public function __construct()
    {
        $this->env = new Environment();
    }

    public function visitP($ctx): mixed
    {
        $this->hoistFunctions($ctx);

        if (!isset($this->functions['main'])) {
            $this->errors[] = [
                'type' => 'Semántico',
                'desc' => 'No se encontró la función main',
                'line' => 0, 'col' => 0,
            ];
            return $this->console;
        }

        $this->callFunction('main', []);
        return $this->console;
    }

    public function visitB($ctx): mixed
    {
        $previous  = $this->env;
        $this->env = new Environment($previous);

        foreach ($ctx->stmt() as $stmt) {
            $this->visit($stmt);
            if ($this->breaking || $this->continuing ) {
                break;
            }

            if ($this->returning) {
                break;
            }
        }

        $this->env = $previous;
        return null;
    }
}