<?php
//   $this->breaking   -> indica que se ejecutó un break
//   $this->continuing -> indica que se ejecutó un continue

trait ForHandler
{
    // for x > 0 { }  (tipo while)
    public function visitForWhileStmt($ctx): mixed
    {
        while (true) {
            $condition = $this->visit($ctx->e());

            if (!is_bool($condition)) {
                $this->errors[] = [
                    'type' => 'Semántico',
                    'desc' => 'La condición del for debe ser de tipo bool',
                    'line' => $ctx->e()->getStart()->getLine(),
                    'col'  => $ctx->e()->getStart()->getCharPositionInLine(),
                ];
                break;
            }

            if (!$condition) break;

            $this->visit($ctx->block());

            // Si se ejecutó un break, salir
            if ($this->breaking) {
                $this->breaking = false;
                break;
            }

            // Si se ejecutó un continue, reiniciar el ciclo
            if ($this->continuing) {
                $this->continuing = false;
                continue;
            }

            if ($this->returning) break;
        }

        return null;
    }

    // for { }  (infinito, necesita break) 
    public function visitForInfiniteStmt($ctx): mixed
    {
        while (true) {
            foreach ($ctx->stmt() as $stmt) {
                $this->visit($stmt);

                if ($this->breaking) {
                    $this->breaking = false;
                    return null;
                }

                if ($this->continuing) {
                    $this->continuing = false;
                    break; // rompe el foreach, reinicia el while
                }

                if ($this->returning) break;
            }

            // Si ya salimos por break, el return de arriba lo maneja
            // Si no hay break nunca, esto es infinito 
        }

        return null;
    }

    // for i := 0; i < 5; i++ { }  
    public function visitForClassicStmt($ctx): mixed
    {
        // Crear scope propio para la variable del for
        $previous  = $this->env;
        $this->env = new Environment($previous);

        // Inicialización: i := 0  o  var i int = 0
        $this->visit($ctx->varForInit());

        while (true) {
            // Condición
            $condition = $this->visit($ctx->e());

            if (!is_bool($condition)) {
                $this->errors[] = [
                    'type' => 'Semántico',
                    'desc' => 'La condición del for debe ser de tipo bool',
                    'line' => $ctx->e()->getStart()->getLine(),
                    'col'  => $ctx->e()->getStart()->getCharPositionInLine(),
                ];
                break;
            }

            if (!$condition) break;

            // Cuerpo del for
            $this->visit($ctx->block());

            if ($this->breaking) {
                $this->breaking = false;
                break;
            }

            if ($this->continuing) {
                $this->continuing = false;
                // aun así ejecuta el post antes de reiniciar
            }

            if ($this->returning) break;

            // Post: i++, i--, i += 1, etc.
            $this->visit($ctx->forPost());
        }

        // Restaurar scope
        $this->env = $previous;
        return null;
    }

    //  Inicialización del for 

    public function visitForShortInit($ctx): mixed
    {
        // i := 0
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());
        $this->env->declare($name, $value);
        return null;
    }

    public function visitForVarInit($ctx): mixed
    {
        // var i int32 = 0
        $name  = $ctx->ID()->getText();
        $value = $this->visit($ctx->e());
        $this->env->declare($name, $value);
        return null;
    }

    // (i++, i--, i += n, i -= n)

    public function visitForIncPost($ctx): mixed
    {
        // i++
        $name = $ctx->ID()->getText();
        try {
            $this->env->set($name, $this->env->get($name) + 1);
        } catch (Exception $e) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    public function visitForDecPost($ctx): mixed
    {
        // i--
        $name = $ctx->ID()->getText();
        try {
            $this->env->set($name, $this->env->get($name) - 1);
        } catch (Exception $e) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    public function visitForPlusAssignPost($ctx): mixed
    {
        // i += n
        $name  = $ctx->ID()->getText();
        $right = $this->visit($ctx->e());
        try {
            $this->env->set($name, $this->env->get($name) + $right);
        } catch (Exception $e) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    public function visitForMinusAssignPost($ctx): mixed
    {
        // i -= n
        $name  = $ctx->ID()->getText();
        $right = $this->visit($ctx->e());
        try {
            $this->env->set($name, $this->env->get($name) - $right);
        } catch (Exception $e) {
            $this->errors[] = ['type' => 'Semántico', 'desc' => $e->getMessage(), 'line' => 0, 'col' => 0];
        }
        return null;
    }

    // Break y Continue 

    public function visitBreakStmt($ctx): mixed
    {
        $this->breaking = true;
        return null;
    }

    public function visitContinueStmt($ctx): mixed
    {
        $this->continuing = true;
        return null;
    }
}