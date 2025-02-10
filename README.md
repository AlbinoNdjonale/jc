# JC

**autor**: Albino Ndjonale &lt;albinondjonale1@gmail.com&gt;
<br>
**versão**: 1.0.0

Jc é framework web feito em php, que visa ser facil e simples de compreender.

## Para iniciar um projecto

```bush
git clone https://github.com/AlbinoNdjonale/jc.git projecto
```

## Estrutura inicial de um projecto JC

```bush
.
│
├── jc
│   ├── index.php
│   ├── middleware.php
│   ├── pages.php
│   ├── qbuilder.php
│   ├── response.php
│   └── util.php
├── index.php
├── README.md
├── .env
└── .htaccess
```

O arquivo ./.env é o arquivo usado para configurar o seu projecto.

Inicialmente ele contera o seguite conteudo

```properties
URL=http://localhost/appname
DEV=true
```

O atributo URL representa a url base da applicação.
O atributo DEV diz se a applicação está no modo de desenvolvimento ao não.

O arquivo ./index.php é o ponto de partida da applicação.

A baixo tu podes ver o seu conteudo inicial

```php
<?php
    require __DIR__.'/jc/index.php';

    use jc\Jc;

    $app = new Jc();

    $app->run();
```

E pronto,  agora tu tens um app criado com jc, com apenas 4 linhas de codigo. Ao abrir seu navegador na porta em que o seu servidor está rodando, tu veras a pagina de boas vindas do JC.

## Paginas

para adicionar paginas no nosso app, basta chamar os metodos get, post, delete, put e route do nosso objecto `Jc`

```php
<?php
    require __DIR__.'/jc/index.php';

    use jc\Jc;

    use jc\response\Response;
    use jc\response\Render;
    use jc\response\RedirectResponse;

    use function jc\url_for;

    $app = new Jc();

    $app->get('/', ['name' => 'home'], function($request) {
        return new Response('HOME');
    });

    $app->get('/prophile/{username}', ['name' => 'prophile'], function($request) {
        $username = $request['GET']['username'];

        return new Response("$username's prophile");
    });

    $app->route('/login', ['name' => 'login', 'methods' => ['GET', 'POST']], function($request) {
        if ($request['METHOD'] == 'GET') return new Render('login');

        $username = $request['POST']['username'];
        $password = $request['POST']['password'];

        if ($username == 'user' && $password == '12345') return new RedirectResponse(url_for('home'));

        return new Render('login');
    });

    $app->run();
```

No codigo acima adicionamos tres paginas ao nosso app. A primeira usamos o metodo get para adicionar uma pagina que só podera ser acessada pelo metodo GET. O metodo usado para adicionar a pagina recebe tres argumentos.

- O caminho da pagina, (`'/'`)
- Um conjunto de configurações da pagina, como status code, name, middlewares, etc, (`['name' => 'home']`)
- Uma função que representa a pagina em si, ela recebe o parametro `$request`, um array que contem as informações da requisição, como METHOD, POST e GET (valores enviados pelo cliente usando os repetivos metodos), COOKIE, etc.

Repara que cada pagina retorna sempre um tipo Response ou um tipo derivado dele.

### Responses

Toda pagina deve retorna uma valor do tipo Response ou derivado.

Todas as responses tem a mesma assinatura.

```php
new Response('valor a ser retornado', $statuscode, $headers, $cookies);
```

Os parametros `headers` e `cookies` são arrays do tipo chave valor.

#### `Response`

este tipo de response é usado para retornar códigos basico de html em forma de string pura.

```php
new Response('<h1>Olá, Mundo!<h1/>');
```

#### `RedirectResponse`

este tipo de response é usado para redirecionar a requisição para uma outra pagina.

```php
new RedirectResponse('http://localhost:80/');
```

se a pagina tiver um nome definido tu podes usar a função url_for para simplificar as coisas.

```php
new RedirectResponse(url_for('nomedapagina'));
```

#### `JSONResponse`

este tipo de response é usado para retornar dados json, ele é usado na criação de api. 

```php
new JSONResponse([
    'name'     => 'user',
    'is_admin' => true
]);
```

#### `FILEResponse`

este tipo de response é usado para retornar arquivos, o seu primeiro argumento é o caminho completo do arquivo.

```php
new FILEResponse('./files/image.jpg');
```

#### `Render`

este tipo de response é usado para retornar código html mais complexo, este código é escrito em um arquivo html separado. o seu primeiro argumento é o nome do arquivo html sem a sua extensão, estes arquivos são armazenados por padrão dentro do directorio `./templates/`, mas isso pode ser alterado ao definir o seu app, `$app = new Jc('static/', 'templates/');`, o primeiro argumento representa o directorio onde os arquivos estaticos são armazenados e o segundo argumento representa o directorio onde são armazenados os templates usados pelo response Render.

OBS. se os parametros não forem definidos eles tomam valorez padrão, `static/` e `templates/`.

```php
new Render('index');
```

Dentro do arquivo html o template, é possivel usar codigo php para tornalos dinamico. Basta adicionar o `'@'` no inicio da linha
que ela será interpretada como php. todo conteudo delimitado por `'{}'`.

OBS. use `'@@'` para que uma linha comece com `'@'` sem ser interpretado como php

veja abaixo o codigo de um template.

```html
<!DOCTYPE html>
<html>
    <head>
        <meta charset = "UTF-8">
        <meta name = "viewport" content = "width=device-width, initial-scale=1.0">
        <title>home</title>
        <link rel="stylesheet" href = "{{ $static("css/style.css") }}">
    </head>

    <body>
        {{ $include("Header") }}
        
        <nav>
            <ul>
                @foreach (["home", "login"] as $value) {
                    <li><a href = {{ $url($value) }}>{{ $value }}</a></li>
                @}
            </ul>
        </nav>

        @if ($username) {
            Olá, {{ $username }}
        @} else {
            Bem Vindo
        }
    </body>
</html>
```

No código acima são usados tres funções predefinidas dos templates.

- `$static`. retorna o caminho completo de um arquivo estático
- `$include`. retorna o conteudo de um outro template
- `$url`. retorna a url da pagina especificada no parametro, ela funciona como a função url_for.

E por fim, vimos também o identificador `$username`, este indentificador não é predefinida, ele precisa ser definido ao chamar o Render.

```php
new Render('index', [
    'username' => 'user'
]);
```

O Render é o unico response que o seu segundo argumento não é o statuscode mas sim um array usado para definir identificadores a serem usados no template. o statuscode fica sendo o terceiro argumento.

Existe uma forma de definir identificadores que faz com que eles sejam globais, ou seja poderam ser acessados em qualquer template, como é o caso dos identificadores predefinidos.

```php
Render::add_var('identificadorglobal', 'valor');
```

## Modularizando o app

A medida que mais paginas são adicionados ao app, mais longo fica o arquivo `./index.php`, e isto não é muito legal e torna a manutenção do codigo demasiadamente complexa.

O Jc permite devidir o seu app em modulos, por exemplo tu podes ter um modulo voltado apenas para paginas web e outro modulo voltado para api.

criaremos um directorio na raiz do projecto. `./routes/`, e dento deste directorio criaremos tres arquivos, `index.php`, `api.php`, `web.api`.

```bush
.
│
├── routes
│   ├── api.php
│   ├── index.php
│   └── web.php
```

No arquivo ./routes/web.php

```php
<?php
    use jc\JCRoute;
    use jc\response\Render;

    $web = new JCRoute();

    $web->get('/', ['name' => 'home'], function($request) {
        return new Render('home');
    });

    $web->get('/prophile/{username}', ['name' => 'prophile'], function($request) {
        $username = $request['GET']['username'];

        return new Render('prophile');
    });

    return $web;
```

Tu deves ter reparado no novo tipo, o `JCRoute`, ele é como o `Jc`, a diferença que há entre eles é que o `Jc` tem mais funcionalidades e cada projecto tera um unico `Jc` definido, ele é a raiz do projecto, ele é o unico que contem o metodo `run`, usado para inicializar o projecto.

No arquivo ./routes/api.php

```php
<?php
    use jc\JCRoute;
    use jc\response\JSONResponse;

    $api = new JCRoute();

    $api->get('/{userid}', ['name' => 'user'], function($request) {
        $users = [
            ['nome' => 'user', 'email' => 'user@example.com'],
            ['nome' => 'user1', 'email' => 'user1@example.com']
        ];
        
        $userid = $request['GET']['userid'];

        if (isset($users[$userid]))
            return new JSONResponse($users[$userid]);

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->get('/', ['name' => 'users'], function($request) {
        return new JSONResonse([
            ['nome' => 'user', 'email' => 'user@example.com'],
            ['nome' => 'user1', 'email' => 'user1@example.com']
        ]);
    });

    $api->post('/', ['name' => 'insertuser', 'response_code' => 201], function($request) {
        if (isset($request['POST']['nome']) && isset($request['POST']['email']))
            return new JSONResponse([
                'detail' => 'usuário criado com sucesso',
                'user'   => [
                    'nome'  => $request['POST']['nome'],
                    'email' => $request['POST']['email']
                ]
            ]);

        return new JSONResponse([
            'detail' => 'dados em falta'
        ], 400);
    });

    return $api;
```

No arquivo ./routes/index.php

```php
<?php
    $api = require_once __DIR__.'/api.php';
    $web = require_once __DIR__.'/web.php';

    use jc\JCRoute;

    $routes = new JCRoute();

    $routes->include_route($api, '/api');
    $routes->include_route($web);

    return $routes;
```

Aqui estamos perante um metodo novo, o metodo include_route é usado para incluir asrotas de um app em outro app, neste caso as rotas dos apps `$web` e `$api` estão sendo incluindo no app `$routes`.

O primeiro argumento deste metodo é o app em si, e o segundo é o prefixo que sera adicionados em todas as urls das paginas desse mesmo app.

Uma vez que os apps `$web` e `$api` foram incluidos no app `$routes`, devemos incluir o este mesmo app no app principal.

Arquivo ./index.php

```php
<?php
    require __DIR__.'/jc/index.php';
    
    $routes = require_once __DIR__.'/routes/index.php';

    use jc\Jc;

    $app = new Jc();

    $app->include_route($routes);

    $app->run();
```

## Construtor de consultas

Um construtor de consultas o Query Builder é uma ferramenta que te possiblita interagir com banco de dados sem a necessidade de usar código sql diretamente.

O Jc traz consigo um Query Builder muito simples de ser usado.

```php
<?php
    use jc\JCRoute;
    use jc\response\JSONResponse;
    use jc\qbuilder\QBuilder;
    use jc\util\Util;

    $api = new JCRoute();

    function db() {
        return new QBuilder(
            'mysql',
            'jc',
            '',
            'localhost',
            'root',
            3306
        );
    }

    $api->get('/{userid}', ['name' => 'user'], function($request) {
        $db = db();

        $userid = $request['GET']['userid'];

        $user = $db->table('user')
            ->select()
            ->where('id = '.QBuilder::prepare($userid))
            ->query()
            ->first();

        if ($user)
            return new JSONResponse($user);

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->get('/', ['name' => 'users'], function($request) {
        $db = db();

        $users = $db->table('user')
            ->select()
            ->query()
            ->all();

        return new JSONResponse($users);
    });

    $api->delete('/{userid}', ['name' => 'deleteuser'], function($request) {
        $db = db();

        $userid = $request['GET']['userid'];

        $user = $db->table('user')
            ->select()
            ->where('id = '.QBuilder::prepare($userid))
            ->query()
            ->first();

        if ($user) {
            $db->table('user')
                ->delete()
                ->where('id = '.QBuilder::prepare($userid))
                ->execute();

                return new JSONResponse([
                    'detail' => 'usuário deletado com sucesso',
                    'user'   => $user
                ]);
        }

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->put('/{userid}', ['name' => 'updateuser'], function($request) {
        $db = db();

        $userid = $request['GET']['userid'];

        $userexist = $db->table('user')
            ->select()
            ->where('id = '.QBuilder::prepare($userid))
            ->query()
            ->exist();

        if ($userexist) {
            $is_valid = Util::is_valid($request['POST'], [
                'username'  => 'string|required',
                'email'     => 'is_email|required',
                'passwordd' => 'string|required|minlength-8'
            ]);

            if ($is_valid[0]) {
                $db->table('user')
                    ->update($request['POST'])
                    ->where('id = '.QBuilder::prepare($userid))
                    ->execute();

                $user = $db->table('user')
                    ->select()
                    ->where('id = '.QBuilder::prepare($userid))
                    ->query()
                    ->first();

                return new JSONResponse([
                    'detail' => 'usuario atualizado com sucesso',
                    'user'   => $user
                ]);
            }

            return new JSONResponse($is_valid[1], 400);
        }

        return new JSONResponse([
            'detail' => 'usuário não encontrado'
        ], 404);
    });

    $api->post('/', ['name' => 'insertuser'], function($request) {
        $db = db();

        $is_valid = Util::is_valid($request['POST'], [
            'username'  => 'string|required',
            'email'     => 'is_email|required',
            'passwordd' => 'string|required|minlength-8'
        ]);

        if ($is_valid[0]) {
            $db->table('user')
                ->insert($request['POST'])
                ->execute();

            $user = $db->table('user')
                ->select()
                ->where('id = max(id)')
                ->query()
                ->first();

            unset($user['max(id)']);

            return new JSONResponse([
                'detail' => 'usuario inserido com sucesso',
                'user'   => $user
            ]);
        }

        return new JSONResponse($is_valid[1], 400);
    });
```

O `QBuilder` é o construtor de consultas do JC, ele é simples e facil de compreender, ao ser inicializado o primeiro parametro indica o tipo de base de dados a ser usado, `mysql` e `sqlite`, no momento ele suporta apenas essas duas opções, o segundo parametro é o nome da base de dados ou o caminho dele, no caso do sqlite, desde o terceiro parametro ao ultimo são aplicaveis apenas quando se trata de uma base de dados do tipo `mysql`, o terceiro parametro é a palvra passe do banco de dados, o quarto parametro é o host do banco de dados, o quinto parametro é o usuário do banco de dados e o sexto parametro é a porta do banco de dados.

A maioria dos metodos do `QBuilder` retornam a si proprio.

Ele contem apenas 3 metodos que não retornam a si proprio

- `first`. use este metodo para pegar o primeiro elemento da lista de resultados de uma busca
- `all`. use este metodo para pegar todos os elementos da lista de resultados de uma busca
- `exist`. use este metodo para verificar se uma busca teve resultado

Ele tem os metodos

- `table`, para definir a tabela
- `select`, para consultar dados na tabela
- `delete`, para deletar dados na tabela
- `update`, para atualizar dados na tabela
- `insert`, para inserir dados na tabela
- `where`, para filtrar as linhas a serem afetadas
- `and_where`, para filtrar as linhas a serem afetadas, ele só é aplicado depois de um `where`, ele concatenara o `where` passado com ele mesmo adicionando o operador `and` no centro
- `or_where`, para filtrar as linhas a serem afetadas, ele só é aplicado depois de um `where`, ele concatenara o `where` passado com ele mesmo adicionando o operador `or` no centro
- `execute`, para executar a sua query depois dela ser construida, este metodo não retorna nenhum valor, use ele quando não precisar do valor renortado por uma consulta
- `query`, para executar a sua query depois dela ser construida, este metodo retorna o resultado da busca

Ele também tras consigo um metodo estatico, o metodo `prepare` é usado para se previnir de ataques do tipo injecção de sql, ele recebe como parametro um valor string e rotorna o mesmo valor, porem bem tratado e eliminado qualquer sinal de vulnerablidade.

No código acima, também apareceu o tipo `Util`, ele é um conjuto de metodos estaticos que adicionam ao Jc, um conjunto de funções praticas e convenientes.

Neste caso usamo o metodo `is_valid`, para validar os valores oriundos do usuário, seu primeiro parametro é um array que contem os tais valores a serem validados, por exemplo, `['nome' => 'user']`, e o segundo parametro é um array com as condições para validar cada campo, por exemplo `'['name' => 'string|required']'`, neste exemplo o metodo só retornara `true` se o campo `name` for do tipo string, e estiver presente, as condições são separadas pelo `|`.

Veja a baixo a lista de condições.

- `string`. O campo precisa ser do tipo `string`
- `int`. O campo precisa ser do tipo `int`
- `float`. O campo precisa ser do tipo `float`
- `bool`. O campo precisa ser do tipo `bool`
- `enum-(value1, value2...)`. O campo precisa ser igual a um dos valores especificados no `enum`
- `is_email`. O campo precisa ser um email
- `required`. O campo é obrigatorio
- `blank`. O campo pode ser igual a `''`
- `length-x`. O campo precisa ter um comprimento igual a x, sendo x qualquer valor inteiro
- `minlength-x`. O campo precisa ter um comprimento de no minimo x, sendo x qualquer valor inteiro
- `maxlength-x`. O campo precisa ter um comprimento de no maximo x, sendo x qualquer valor inteiro

## Middlewares

Middlewares são ações executadas antes e/ou depois de uma pagina.

A baixo um código que cria um middleware

```php
<?php
    function middleware($view) {
        return function($request) use ($view) {

            // execute alguma coisa antes da view

            $response = $view($request);

            // execute alguma coisa depois da view

            return $response;
        };
    }
```

O codigo acima cria um middleware, ele pode ser adicionado a um a todas a paginas de um app ou a paginas especificas.

```php
<?php
    use jc\Jc;
    use jc\response\Response;

    $app = new Jc();
    
    // aplicando o middleare a todas as paginas deste app
    $app->add_middleware(middleware);

    // aplicando o middleware a uma unica pagina
    $app->get('/', ['middlewares' => [middleware]], function($request) {
        return new Response('home');
    });
```

### Middlewares predefinidos

O Jc traz consigo um conjunto de middlewares predefinidos e tornaram o desenvolvimento da aplicação mais simples.

#### cors

Este é um middleware usado para definir quais host podem interagir com a sua app.

```php
<?php
    use jc\Jc;
    
    $app = new Jc();

    $app->add_middleware(Middleware::cors([
        'allow-origin' => 'http://localhost:5317'
    ]));

    $app->run();
```

**Para usar os middlewares que vem a baixo**

tu deves definir as informações da sua base de dados no arquivo de configuração(.env).

```properties
DATABASE=mysql
DATABASENAME=jc
DATABASEPASSWORD=
DATABASEHOST=localhost
DATABASEUSER=root
DATABASEPORT=3306
```

também é necessario definir as seguintes tabelas na sua base de dados.

```sql
create table user (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `first_name` VARCHAR(50),
    `last_name` VARCHAR(50),
    `date_joined` DATE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATE,
    `is_active` BOOLEAN NOT NULL DEFAULT 0,
    `is_admin` BOOLEAN NOT NULL,
    `gender` ENUM('M', 'F'),
    `birth` DATE,
    `email` VARCHAR(250) NOT NULL,
    `password` VARCHAR(66) NOT NULL,
    PRIMARY KEY (`id`)
);
```

#### csrftoken

Este é um middleware usado para dar mais segurança aos formularios web.

```php
<?php
    use jc\JCRoute;
    
    $web = new JCRoute();

    $web->add_middleware(Middleware::csrftoken());

    return $web;
```

Este middleware cria um identificador global para ser usado nos templates, este identificador é o `$csrftoken`, todos os formulários com o metodo diferente de GET devem conter o seguinte conteudo: `{{ $csrftoken() }}`

#### authuser

Este middleware analiza os dados enviados pelo usuario e tenta autenticar o usuário com os mesmos dados, ele cria o identificador global `$user` para ser usado nos templates.

OBS. este middleware só é compativel com paginas web e não com api.