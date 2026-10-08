<?php

declare(strict_types=1);

namespace App\Seguranca\Rbac;

enum Permissao: string
{
    case VerRelatorios = 'relatorio.ver';
    case EditarPerfil = 'perfil.editar';
    case EditarProprioPerfil = 'perfil.editar-proprio';
}
