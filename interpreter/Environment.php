<?php

class Environment
{
    private array $variables = [];
    private ?Environment $parent;
    public string $scopesName;
    private array $constants = [];

    public function __construct(?Environment $parent = null, string $scopeName = 'global')
    {
        $this->parent = $parent;
        $this->scopeName = $scopeName;
    }

    // Declara una variable NUEVA 
    public function declare(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    // Obtiene el valor buscando en la cadena de scopes
    public function get(string $name): mixed
    {
        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name];
        }
        if ($this->parent !== null) {
            return $this->parent->get($name);
        }
        throw new Exception("Variable '$name' no declarada");
    }

    // Asigna valor a variable ya existente 
    public function set(string $name, mixed $value): void
    {
        if (array_key_exists($name, $this->variables)) {
            if ($this->isConst($name)) {
                throw new Exception("No se puede editar esta contstante '$name'");
            }
            $this->variables[$name] = $value;
            return;
        }
        if ($this->parent !== null) {
            $this->parent->set($name,$value);
            return;
        }
        throw new Exception("VARIABLE '$name' no declarada");
        
    }

    // Verifica si existe en el actual entorno (sin buscar en padres)
    public function existsLocally(string $name): bool
    {
        return array_key_exists($name, $this->variables);
    }

    public function declareConst(string $name): void
    {
        $this->constants[] = $name;
    }

    public function isConst(string $name): bool
    {
        return in_array($name, $this->constants);
    }
}