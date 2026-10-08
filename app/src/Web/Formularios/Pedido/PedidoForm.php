<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Hydrator\Attribute\Parameter\Collection;
use Yiisoft\Validator\Rule\Count;
use Yiisoft\Validator\Rule\Each;
use Yiisoft\Validator\Rule\Nested;
use Yiisoft\Validator\Rule\Required;

final class PedidoForm extends FormModel
{
    #[Required(message: 'Informe o nome do cliente.')]
    public string $cliente = '';

    #[Nested]
    public EnderecoForm $endereco;

    /** @var ItemForm[] */
    #[Collection(ItemForm::class)]
    #[Count(min: 1, lessThanMinMessage: 'Adicione pelo menos um item.')]
    #[Each(new Nested())]
    public array $itens = [];

    public function __construct(int $linhas = 3)
    {
        $this->endereco = new EnderecoForm();

        for ($i = 0; $i < $linhas; $i++) {
            $this->itens[] = new ItemForm();
        }
    }

    /**
     * Descarta as linhas que o usuário deixou totalmente em branco.
     */
    public function removerItensVazios(): void
    {
        $this->itens = array_values(array_filter(
            $this->itens,
            static fn(ItemForm $item): bool => !$item->estaVazio(),
        ));
    }
}
