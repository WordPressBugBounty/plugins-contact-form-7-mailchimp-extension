<?php
/**
 * Product adapter contract.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

interface ProductAdapterInterface {

	public function product_slug(): string;

	public function product_version(): string;

	public function signal_sharing_enabled(): bool;

	public function install_id(): string;

	public function contract(): array;

	public function snapshot(): array;
}
