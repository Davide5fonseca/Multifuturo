<?php

/*
|--------------------------------------------------------------------------
| Textos legais (pt-PT)
|--------------------------------------------------------------------------
|
| Política de privacidade, termos e condições, política de cookies e página
| institucional. Cada documento é uma lista de secções (título + parágrafos).
| Os dados da agência (:name, :email, :address, :ami) vêm de config('agency').
|
| NOTA: são minutas de trabalho redigidas para o contexto do site (RGPD, Lei
| n.º 15/2013, DL n.º 74/2017). Devem ser revistas por quem responde pela
| conformidade legal da agência antes da publicação. A versão da política de
| privacidade em vigor é config('agency.privacy_policy_version') e é gravada
| em cada lead — ao alterar o texto, atualizar a versão.
|
*/

return [

    'privacy' => [
        'title' => 'Política de privacidade',
        'lead' => 'Como a :name trata os dados pessoais recolhidos neste site.',
        'updated' => 'Versão :version',
        'sections' => [
            [
                'title' => '1. Responsável pelo tratamento',
                'paragraphs' => [
                    'A entidade responsável pelo tratamento dos dados pessoais recolhidos neste site é a :name, empresa de mediação imobiliária titular da licença AMI n.º :ami, com sede em :address. Para qualquer questão relacionada com os seus dados pode contactar-nos através de :email.',
                ],
            ],
            [
                'title' => '2. Que dados recolhemos',
                'paragraphs' => [
                    'Formulários de contacto, de pedido de informação sobre um imóvel e de avaliação: nome, endereço de email, telefone (opcional), mensagem, o imóvel a que o pedido diz respeito e, no pedido de avaliação, os dados do imóvel que indicar (morada, concelho, tipo, tipologia, área, estado).',
                    'Com cada pedido registamos ainda a data e hora, a versão desta política que lhe foi apresentada, as opções de consentimento que selecionou e um identificador técnico derivado do seu endereço IP (um resumo criptográfico irreversível, não o IP em si), utilizado exclusivamente para prevenir abusos e envios automáticos.',
                    'Favoritos: os imóveis que guarda como favoritos ficam apenas no seu dispositivo (armazenamento local do navegador). Não são enviados nem guardados nos nossos servidores.',
                    'Cookies: ver a Política de cookies.',
                ],
            ],
            [
                'title' => '3. Finalidades e fundamentos',
                'paragraphs' => [
                    'Responder aos seus pedidos de informação, contacto e avaliação e prestar o serviço de mediação imobiliária que solicita — com fundamento nas diligências pré-contratuais a seu pedido (art. 6.º, n.º 1, alínea b), do RGPD).',
                    'Contactá-lo sobre o pedido concreto que fez, através dos meios que indicou — apenas se der esse consentimento na respetiva caixa (art. 6.º, n.º 1, alínea a)).',
                    'Enviar-lhe comunicações comerciais, novidades e imóveis selecionados por email — apenas se der esse consentimento na respetiva caixa, que pode retirar a qualquer momento (art. 6.º, n.º 1, alínea a)).',
                    'Garantir a segurança do site e prevenir utilizações abusivas — com fundamento no nosso interesse legítimo (art. 6.º, n.º 1, alínea f)).',
                    'Cumprir obrigações legais aplicáveis à atividade de mediação imobiliária (art. 6.º, n.º 1, alínea c)).',
                ],
            ],
            [
                'title' => '4. Com quem partilhamos os dados',
                'paragraphs' => [
                    'Os pedidos que nos envia são registados no sistema de gestão da agência (CRM), fornecido pela CASAFARI, que atua como subcontratante e trata os dados exclusivamente por nossa conta e segundo as nossas instruções.',
                    'Recorremos ainda a prestadores de alojamento e de envio de email, igualmente vinculados por contrato à confidencialidade e segurança dos dados.',
                    'Não vendemos nem cedemos os seus dados a terceiros para fins próprios destes. Não são realizadas transferências para fora do Espaço Económico Europeu sem as garantias exigidas pelo RGPD.',
                ],
            ],
            [
                'title' => '5. Durante quanto tempo conservamos os dados',
                'paragraphs' => [
                    'Os dados dos pedidos são conservados durante o tempo necessário à sua resposta e acompanhamento e, existindo relação contratual, durante os prazos legais aplicáveis à atividade de mediação imobiliária. Os consentimentos para comunicações comerciais valem até serem retirados.',
                    'Os registos técnicos de segurança (identificador derivado do IP) são eliminados no prazo máximo de 12 meses.',
                ],
            ],
            [
                'title' => '6. Os seus direitos',
                'paragraphs' => [
                    'Tem o direito de aceder aos seus dados, de os retificar ou apagar, de limitar ou opor-se ao tratamento, à portabilidade, e de retirar a qualquer momento os consentimentos que tenha dado, sem que isso afete a licitude do tratamento anterior. Para exercer estes direitos, escreva-nos para :email.',
                    'Tem ainda o direito de apresentar reclamação à Comissão Nacional de Proteção de Dados (CNPD — www.cnpd.pt).',
                ],
            ],
            [
                'title' => '7. Segurança',
                'paragraphs' => [
                    'Aplicamos medidas técnicas e organizativas adequadas para proteger os dados contra acesso, alteração, divulgação ou destruição não autorizados: comunicação cifrada (HTTPS), controlo de acessos, registo de operações e minimização dos dados recolhidos.',
                ],
            ],
            [
                'title' => '8. Alterações',
                'paragraphs' => [
                    'Esta política pode ser atualizada. A versão em vigor está sempre disponível nesta página, identificada pela data. Cada pedido que nos envia fica associado à versão que lhe foi apresentada nesse momento.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Termos e condições',
        'lead' => 'Condições de utilização do site da :name.',
        'sections' => [
            [
                'title' => '1. Identificação',
                'paragraphs' => [
                    'Este site é propriedade da :name, empresa de mediação imobiliária licenciada pelo IMPIC com o número AMI :ami, com sede em :address, contactável através de :email.',
                ],
            ],
            [
                'title' => '2. Informação sobre imóveis',
                'paragraphs' => [
                    'A informação apresentada sobre cada imóvel (preços, áreas, características, fotografias, certificado energético) provém do sistema de gestão da agência e é atualizada regularmente, mas tem carácter meramente informativo e não constitui proposta contratual. Os elementos essenciais de cada negócio são confirmados por escrito no âmbito do respetivo processo de mediação.',
                    'Um imóvel pode deixar de estar disponível a qualquer momento. As áreas indicadas são as constantes da documentação disponível e podem ser objeto de confirmação. A localização apresentada nos mapas é aproximada quando o proprietário assim o solicite.',
                ],
            ],
            [
                'title' => '3. Utilização do site',
                'paragraphs' => [
                    'O utilizador compromete-se a utilizar o site de forma lícita, sem prejudicar o seu funcionamento, sem recolher automaticamente informação nele contida e sem enviar conteúdos abusivos através dos formulários. Reservamo-nos o direito de bloquear utilizações abusivas.',
                ],
            ],
            [
                'title' => '4. Propriedade intelectual',
                'paragraphs' => [
                    'Os textos, fotografias, marcas e restante conteúdo deste site pertencem à :name ou aos seus legítimos titulares e não podem ser reproduzidos sem autorização, salvo para uso pessoal e não comercial.',
                ],
            ],
            [
                'title' => '5. Responsabilidade',
                'paragraphs' => [
                    'Procuramos manter o site disponível e a informação correta, mas não garantimos a ausência de erros ou interrupções. Não somos responsáveis por danos resultantes da utilização do site ou de sites de terceiros para os quais este remeta.',
                ],
            ],
            [
                'title' => '6. Reclamações e resolução de litígios',
                'paragraphs' => [
                    'Dispomos de Livro de Reclamações em formato físico e eletrónico (www.livroreclamacoes.pt), conforme a lei. Em caso de litígio de consumo, o consumidor pode recorrer a uma entidade de resolução alternativa de litígios; a lista está disponível no Portal do Consumidor (www.consumidor.gov.pt).',
                ],
            ],
            [
                'title' => '7. Lei aplicável',
                'paragraphs' => [
                    'Estes termos regem-se pela lei portuguesa. Para qualquer litígio é competente o tribunal da comarca da sede da :name, sem prejuízo das normas imperativas de proteção do consumidor.',
                ],
            ],
        ],
    ],

    'cookies' => [
        'title' => 'Política de cookies',
        'lead' => 'Que cookies e armazenamento local este site utiliza, e como os pode gerir.',
        'sections' => [
            [
                'title' => '1. O que são',
                'paragraphs' => [
                    'Cookies são pequenos ficheiros guardados no seu dispositivo pelo navegador. Usamos também o armazenamento local do navegador (localStorage), que funciona de forma semelhante mas não é enviado ao servidor.',
                ],
            ],
            [
                'title' => '2. Cookies estritamente necessários',
                'paragraphs' => [
                    'Indispensáveis ao funcionamento do site; não requerem consentimento e não podem ser desativados. Sessão (laravel_session): mantém a sua sessão de navegação, incluindo mensagens de confirmação dos formulários — dura o tempo da sessão. Proteção contra pedidos forjados (XSRF-TOKEN): protege os formulários — dura o tempo da sessão. Preferências de cookies (:consent_cookie): guarda as escolhas que fez neste aviso — 6 meses.',
                    'Armazenamento local: favoritos (multifuturo:favoritos) — os imóveis que guardou, apenas no seu dispositivo, até os remover ou limpar os dados do navegador.',
                ],
            ],
            [
                'title' => '3. Cookies de análise e de marketing',
                'paragraphs' => [
                    'Neste momento o site não utiliza cookies de análise (estatísticas de utilização) nem de marketing. Se vierem a ser introduzidos, só serão ativados depois de dar o seu consentimento neste aviso, categoria a categoria, e esta página será atualizada com a sua descrição, finalidade e duração.',
                ],
            ],
            [
                'title' => '4. Conteúdos de terceiros',
                'paragraphs' => [
                    'Os mapas das fichas de imóvel são fornecidos pelo OpenStreetMap e só são carregados quando clica em "Mostrar mapa". Até esse momento não é feito qualquer pedido a servidores externos. As fotografias dos imóveis são servidas a partir do sistema de gestão da agência.',
                ],
            ],
            [
                'title' => '5. Como gerir',
                'paragraphs' => [
                    'Pode alterar as suas escolhas a qualquer momento através da ligação "Gerir cookies" no rodapé do site. Pode também bloquear ou apagar cookies nas definições do navegador; nesse caso, algumas funcionalidades (por exemplo, o envio de formulários) podem deixar de funcionar.',
                ],
            ],
        ],
    ],

    'about' => [
        'title' => 'A agência',
        'lead' => 'Mediação imobiliária com acompanhamento próximo, do primeiro contacto à escritura.',
        'sections' => [
            [
                'title' => 'Quem somos',
                'paragraphs' => [
                    'A :name é uma empresa de mediação imobiliária licenciada (AMI :ami), dedicada à compra, venda e arrendamento de imóveis residenciais e comerciais.',
                    'Trabalhamos com uma carteira própria, atualizada diariamente, e com consultores que conhecem o mercado local e acompanham cada cliente de forma pessoal e transparente.',
                ],
            ],
            [
                'title' => 'Como trabalhamos',
                'paragraphs' => [
                    'Avaliamos com rigor, com base em dados reais do mercado. Promovemos cada imóvel com fotografia profissional e divulgação nos principais canais. Tratamos da documentação e acompanhamos a negociação e a escritura, explicando cada passo em linguagem clara.',
                ],
            ],
            [
                'title' => 'Onde estamos',
                'paragraphs' => [
                    ':address',
                    'Telefone: :phone · Email: :email',
                ],
            ],
        ],
    ],

    'consent' => [
        'title' => 'Cookies e privacidade',
        'text' => 'Usamos cookies estritamente necessários ao funcionamento do site. Só usamos cookies de análise ou de marketing se o autorizar — pode escolher por categoria. Saiba mais na :link.',
        'link' => 'política de cookies',
        'accept_all' => 'Aceitar tudo',
        'reject_all' => 'Recusar não essenciais',
        'customize' => 'Personalizar',
        'save' => 'Guardar escolhas',
        'manage' => 'Gerir cookies',
        'necessary' => 'Estritamente necessários',
        'necessary_desc' => 'Sessão, segurança dos formulários e memória destas escolhas. Sempre ativos.',
        'analytics' => 'Análise',
        'analytics_desc' => 'Estatísticas anónimas de utilização, para melhorar o site. Atualmente não há nenhuma ferramenta ativa.',
        'marketing' => 'Marketing',
        'marketing_desc' => 'Personalização de anúncios em plataformas de terceiros. Atualmente não há nenhuma ferramenta ativa.',
        'always_on' => 'Sempre ativo',
    ],

];
