grammar Golampi;

//  PROGRAMA
program
    : decl* EOF                                     # P
    ;

decl
    : funcDecl                                      # FunctionDeclaration
    | 'const' ID type_ '=' e ';'?                   # ConstDeclGlobal
    ;


//  FUNCIÓN
funcDecl
    : 'func' ID '(' (param (',' param)*)? ')' block                                            # FuncDeclVoid
    | 'func' ID '(' (param (',' param)*)? ')' returnType block                                 # FuncDeclReturn
    | 'func' ID '(' (param (',' param)*)? ')' '(' returnType (',' returnType)* ')' block       # FuncDeclMultiReturn
    ;

param
    : ID type_    # Parametro
    | ID arrayType1D type_              # ParametroArray1D
    | ID arrayType2D type_              # ParametroArray2D
    | ID '*' arrayType1D type_          # ParametroPointerArray1D
    | ID '*' arrayType2D type_          # ParametroPointerArray2D
    ;

//  BLOQUE
block
    : '{' stmt* '}'                                 # B
    ;


//  SENTENCIAS
stmt
    : 'var' ID arrayType1D type_ '=' arrayLit1D ';'?# VarArray1DInit
    | 'var' ID arrayType1D type_ ';'?               # VarArray1D
    | 'var' ID arrayType2D type_ ';'?               # VarArray2D
    | 'var' ID arrayType2D type_ '=' arrayLit2D ';'?# VarArray2DInit
    | 'var' ID type_ '=' e ';'?                     # VarDeclInit
    | 'var' ID type_ ';'?                           # VarDeclEmpty
    | 'var' ID (',' ID)+ type_ '=' e (',' e)* ';'?  # VarDeclMulti
    | 'const' ID type_ '=' e ';'?                   # ConstDeclStmt
    | ID ':=' e ';'?                                # ShortVarDecl
    | ID ':=' arrayLit1D ';'?                       # ShortVarArray1D
    | ID ':=' arrayLit2D ';'?                       # ShortVarArray2D
    | ID (',' ID)+ ':=' e (',' e)* ';'?             # MultiShortVarDecl
    | ID '=' e ';'?                                 # AssignStmt
    | ID '+=' e ';'?                                # PlusAssignStmt
    | ID '-=' e ';'?                                # MinusAssignStmt
    | ID '*=' e ';'?                                # StarAssignStmt
    | ID '/=' e ';'?                                # SlashAssignStmt
    | ID '++'  ';'?                                 # IncStmt
    | ID '--'  ';'?                                 # DecStmt
    | '*' ID '=' e ';'?                             # DerefAssignStmt
    | ID '[' e ']' '=' e ';'?                       # ArrayAssign1D
    | ID '[' e ']' '[' e ']' '=' e ';'?             # ArrayAssign2D
    | 'fmt.Println' '(' (e (',' e)*)? ')' ';'?      # PrintlnStmt
    | ID '(' (e (',' e)*)? ')' ';'?                 # FuncCallStmt
    | 'if' e block ('else' block)? ';'?             # IfStmt
    | 'if' e block 'else' stmt                      # IfElseIfStmt
    | 'for' e block ';'?                            # ForWhileStmt
    | 'for' '{' stmt* '}' ';'?                      # ForInfiniteStmt
    | 'for' varForInit ';' e ';' forPost block ';'? # ForClassicStmt
    | 'switch' e? '{' switchCase* '}' ';'?          # SwitchStmt
    | 'return' (e (',' e)* ';'*)?                   # ReturnStmt
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

arrayType1D
    : '[' INT_LIT ']'
    ;

arrayType2D
    : '[' INT_LIT ']' '[' INT_LIT ']'
    ;

arrayLit1D
    : '[' INT_LIT ']' type_ '{' (e (',' e)*)? '}'
    ;

arrayLit2D
    : '[' INT_LIT ']' '[' INT_LIT ']' type_
        '{' (arrayRow (',' arrayRow)* ','?)? '}'
    ;

arrayRow
    : '{' (e (',' e)*)? '}'
    ;

switchCase
    : 'case' e ':' stmt*                            # CaseStmt
    | 'default' ':' stmt*                           # DefaultStmt
    ;

returnType
    : type_                 # ReturnTypeSimple
    | arrayType1D type_     # ReturnTypeArray1D
    | arrayType2D type_     # ReturnTypeArray2D
    ;

//  TIPOS
type_
    : 'int32'                                       # TypeInt32
    | 'int'                                         # TypeInt
    | 'float32'                                     # TypeFloat32
    | 'bool'                                        # TypeBool
    | 'rune'                                        # TypeRune
    | 'string'                                      # TypeString
    | '*' type_                                     # TypePointer
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
    | 'substr' '(' e ',' e ',' e ')'                # SubstrExpr
    | 'typeOf' '(' e ')'                            # TypeOfExpr
    | ID '(' (e (',' e)*)? ')'                      # FuncCallExpr
    | BOOL_LIT                                      # BoolLit
    | INT_LIT                                       # IntLit
    | FLOAT_LIT                                     # FloatLit
    | STRING_LIT                                    # StringLit
    | RUNE_LIT                                      # RuneLit
    | 'nil'                                         # NilLit
    | ID                                            # IdExpr
    | ID '[' e ']'                                  # ArrayAccess1D
    | ID '[' e ']' '[' e ']'                        # ArrayAccess2D
    | '-' e                                         # NegExpr
    | '!' e                                         # NotExpr
    | '&' ID                                        # RefExpr
    | '*' ID                                        # DerefExpr
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
