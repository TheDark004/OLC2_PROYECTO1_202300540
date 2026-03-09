grammar Golampi;

//  PROGRAMA
program
    : decl* EOF                                     # P
    ;

decl
    : funcDecl                                      # FunctionDeclaration
    ;


//  FUNCIÓN
funcDecl
    : 'func' ID '(' (param (',' param)*)? ')' block           # FuncDeclVoid
    | 'func' ID '(' (param (',' param)*)? ')' type_ block     # FuncDeclReturn
    ;

param
    : ID type_                                                  # Parametro
    ;

//  BLOQUE
block
    : '{' stmt* '}'                                 # B
    ;


//  SENTENCIAS
stmt
    : 'var' ID type_ '=' e ';'?                     # VarDeclInit
    | 'var' ID type_ ';'?                           # VarDeclEmpty
    | ID ':=' e ';'?                                # ShortVarDecl
    | ID '=' e ';'?                                 # AssignStmt
    | ID '+=' e ';'?                                # PlusAssignStmt
    | ID '-=' e ';'?                                # MinusAssignStmt
    | ID '*=' e ';'?                                # StarAssignStmt
    | ID '/=' e ';'?                                # SlashAssignStmt
    | ID '++'  ';'?                                 # IncStmt
    | ID '--'  ';'?                                 # DecStmt
    | 'fmt.Println' '(' (e (',' e)*)? ')' ';'?      # PrintlnStmt
    | ID '(' (e (',' e)*)? ')' ';'?                 # FuncCallStmt
    | 'if' e block ('else' block)? ';'?             # IfStmt
    | 'if' e block 'else' stmt                      # IfElseIfStmt
    | 'for' e block ';'?                            # ForWhileStmt
    | 'for' '{' stmt* '}' ';'?                      # ForInfiniteStmt
    | 'for' varForInit ';' e ';' forPost block ';'? # ForClassicStmt
    | 'return' e? ';'?                              # ReturnStmt
    | 'break' ';'?                                  # BreakStmt
    | 'continue' ';'?                               # ContinueStmt
    ;

varForInit
    : 'var' ID type_ '=' e                          # ForVarInit
    | ID ':=' e                                     # ForShortInit
    ;

forPost
    : ID '++'                                       # ForIncPost
    | ID '--'                                       # ForDecPost
    | ID '+=' e                                     # ForPlusAssignPost
    | ID '-=' e                                     # ForMinusAssignPost
    ;


//  TIPOS
type_
    : 'int32'                                       # TypeInt32
    | 'int'                                         # TypeInt
    | 'float32'                                     # TypeFloat32
    | 'bool'                                        # TypeBool
    | 'rune'                                        # TypeRune
    | 'string'                                      # TypeString
    ;

//  EXPRESIONES
//  Precedencia (de menor a mayor):
//    1. lógico OR  ||
//    2. lógico AND &&
//    3. igualdad   == !=
//    4. relacional < > <= >=
//    5. suma       + -
//    6. producto   * / %
//    7. unario     - !
//    8. primario   literal, ID, (e)


e
    : '(' e ')'                                     # GroupExpr
    | 'fmt.Println' '(' (e (',' e)*)? ')'           # PrintlnExpr
    | 'len' '(' e ')'                               # LenExpr
    | 'now' '(' ')'                                 # NowExpr
    | 'substr' '(' e ',' e ',' e ')'               # SubstrExpr
    | 'typeOf' '(' e ')'                            # TypeOfExpr
    | ID '(' (e (',' e)*)? ')'                      # FuncCallExpr
    | BOOL_LIT                                      # BoolLit
    | INT_LIT                                       # IntLit
    | FLOAT_LIT                                     # FloatLit
    | STRING_LIT                                    # StringLit
    | RUNE_LIT                                      # RuneLit
    | 'nil'                                         # NilLit
    | ID                                            # IdExpr
    | '-' e                                         # NegExpr
    | '!' e                                         # NotExpr
    | e op=('*'|'/'|'%') e                          # MulExpr
    | e op=('+'|'-') e                              # AddExpr
    | e op=('<'|'>'|'<='|'>=') e                    # RelExpr
    | e op=('=='|'!=') e                            # EqExpr
    | e op='&&' e                                   # AndExpr
    | e op='||' e                                   # OrExpr
    ;


//  TOKENS

BOOL_LIT    : 'true' | 'false' ;

INT_LIT     : [0-9]+ ;
FLOAT_LIT   : [0-9]+ '.' [0-9]+ ;

RUNE_LIT    : '\'' ( ~['\\\r\n] | '\\' . ) '\'' ;
STRING_LIT  : '"'  ( ~["\\\r\n] | '\\' . )* '"' ;

ID          : [a-zA-Z_][a-zA-Z0-9_]* ;

LINE_COMMENT : '//' ~[\r\n]* -> skip ;
BLOCK_COMMENT: '/*' .*? '*/' -> skip ;
WS           : [ \t\r\n]+ -> skip ;
