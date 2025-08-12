<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelClientetag
 * 
 * @property int $fk_cliente_id
 * @property int $fk_tag_id
 * 
 * @property Cliente $cliente
 * @property Tag $tag
 *
 * @package App\Models
 */
class RelClientetag extends Model
{
	protected $table = 'rel_clientetag';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'fk_tag_id' => 'int'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function tag()
	{
		return $this->belongsTo(Tag::class, 'fk_tag_id');
	}
}
