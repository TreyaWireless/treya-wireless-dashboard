<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CClock extends CDiv {

	private $width;
	private $height;
	private $is_enabled = true;

	public function __construct() {
		parent::__construct();

		$this->addClass(ZBX_STYLE_CLOCK);
	}

	public function setWidth($value) {
		$this->width = $value;

		return $this;
	}

	public function setHeight($value) {
		$this->height = $value;

		return $this;
	}

	public function setEnabled($is_enabled) {
		$this->is_enabled = $is_enabled;

		return $this;
	}

	private function makeClockDefs() {
		return [
			(new CTag('defs', true))->addItem([
				// Drop shadow for hands and numbers
				(new CTag('filter', true))
					->setAttribute('id', 'shadow-3d')
					->setAttribute('x', '-20%')
					->setAttribute('y', '-20%')
					->setAttribute('width', '140%')
					->setAttribute('height', '140%')
					->addItem(
						(new CTag('feDropShadow', true))
							->setAttribute('dx', '0.6')
							->setAttribute('dy', '1.2')
							->setAttribute('stdDeviation', '0.5')
							->setAttribute('flood-color', '#000000')
							->setAttribute('flood-opacity', '0.6')
					),
				// Inner shadow for bezel ring
				(new CTag('filter', true))
					->setAttribute('id', 'bezel-shadow')
					->addItem([
						(new CTag('feOffset', true))->setAttribute('dx', '1')->setAttribute('dy', '1'),
						(new CTag('feGaussianBlur', true))->setAttribute('stdDeviation', '1.2')->setAttribute('result', 'offset-blur'),
						(new CTag('feComposite', true))->setAttribute('operator', 'out')->setAttribute('in', 'SourceGraphic')->setAttribute('in2', 'offset-blur')->setAttribute('result', 'inverse'),
						(new CTag('feFlood', true))->setAttribute('flood-color', 'black')->setAttribute('flood-opacity', '0.4')->setAttribute('result', 'color'),
						(new CTag('feComposite', true))->setAttribute('operator', 'in')->setAttribute('in', 'color')->setAttribute('in2', 'inverse')->setAttribute('result', 'shadow'),
						(new CTag('feComposite', true))->setAttribute('operator', 'over')->setAttribute('in', 'shadow')->setAttribute('in2', 'SourceGraphic')
					]),
				// Soft white dial gradient
				(new CTag('radialGradient', true))
					->setAttribute('id', 'dial-grad')
					->setAttribute('cx', '50%')
					->setAttribute('cy', '50%')
					->setAttribute('r', '50%')
					->addItem([
						(new CTag('stop', true))->setAttribute('offset', '0%')->setAttribute('stop-color', '#ffffff'),
						(new CTag('stop', true))->setAttribute('offset', '80%')->setAttribute('stop-color', '#f8f9fa'),
						(new CTag('stop', true))->setAttribute('offset', '100%')->setAttribute('stop-color', '#e2e5e9')
					])
			])
		];
	}

	private function makeClockLine($width, $height, $x, $y, $deg) {
		return (new CTag('rect', true))
			->setAttribute('width', $width)
			->setAttribute('height', $height)
			->setAttribute('x', $x)
			->setAttribute('y', $y)
			->setAttribute('fill', '#111111')
			->setAttribute('transform', 'rotate('.$deg.' 50 50)')
			->addClass(ZBX_STYLE_CLOCK_LINES);
	}

	private function makeText($text, $x, $y, $size, $is_bold = true) {
		return (new CTag('text', true))
			->setAttribute('x', $x)
			->setAttribute('y', $y)
			->setAttribute('fill', '#111111')
			->setAttribute('font-size', $size)
			->setAttribute('font-family', 'Arial, Helvetica, sans-serif')
			->setAttribute('font-weight', $is_bold ? 'bold' : 'normal')
			->setAttribute('text-anchor', 'middle')
			->setAttribute('dominant-baseline', 'middle')
			->setAttribute('filter', 'url(#shadow-3d)')
			->addItem($text);
	}

	private function makeClockFace() {
		$face = [
			// Outer Bezel Ring (creates 3D border)
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '49.5')
				->setAttribute('fill', '#f5f6f8')
				->setAttribute('stroke', '#cccccc')
				->setAttribute('stroke-width', '0.5')
				->setAttribute('filter', 'url(#bezel-shadow)'),
			
			// Inner Dial (Face)
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '46')
				->setAttribute('fill', 'url(#dial-grad)'),

			// Ticks (Hour lines)
			$this->makeClockLine('1.0', '4', '49.5', '5', '30'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '60'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '90'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '120'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '150'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '180'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '210'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '240'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '270'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '300'),
			$this->makeClockLine('1.0', '4', '49.5', '5', '330'),
			$this->makeClockLine('1.5', '5', '49.25', '5', '0'),

			// Big Hour Numbers
			$this->makeText('12', '50', '16.5', '9.5'),
			$this->makeText('1', '66.5', '21.0', '9.5'),
			$this->makeText('2', '78.5', '33.0', '9.5'),
			$this->makeText('3', '83.0', '50.0', '9.5'),
			$this->makeText('4', '78.5', '67.0', '9.5'),
			$this->makeText('5', '66.5', '79.0', '9.5'),
			$this->makeText('6', '50', '83.5', '9.5'),
			$this->makeText('7', '33.5', '79.0', '9.5'),
			$this->makeText('8', '21.5', '67.0', '9.5'),
			$this->makeText('9', '17.0', '50.0', '9.5'),
			$this->makeText('10', '21.5', '33.0', '9.5'),
			$this->makeText('11', '33.5', '21.0', '9.5'),

			// Small Minute Numbers
			$this->makeText('55', '29.0', '11.0', '4.5', false),
			$this->makeText('5', '71.0', '11.0', '4.5', false),
			$this->makeText('10', '88.5', '27.0', '4.5', false),
			$this->makeText('15', '92.5', '50.0', '4.5', false),
			$this->makeText('20', '88.5', '73.0', '4.5', false),
			$this->makeText('25', '71.0', '89.0', '4.5', false),
			$this->makeText('30', '50.0', '93.0', '4.5', false),
			$this->makeText('35', '29.0', '89.0', '4.5', false),
			$this->makeText('40', '11.5', '73.0', '4.5', false),
			$this->makeText('45', '7.5', '50.0', '4.5', false),
			$this->makeText('50', '11.5', '27.0', '4.5', false),

			// Treya Wireless green/orange compact logo at bottom-center
			(new CTag('image', true))
				->setAttribute('href', 'local/logo_sidebar_compact_trans.svg')
				->setAttribute('x', '42.0')
				->setAttribute('y', '60.0')
				->setAttribute('width', '16')
				->setAttribute('height', '16')
				->setAttribute('filter', 'url(#shadow-3d)')
		];

		return $face;
	}

	private function makeClockHands() {
		return [
			// Hour hand
			(new CTag('rect', true))
				->setAttribute('width', '3.5')
				->setAttribute('height', '23')
				->setAttribute('x', '48.25')
				->setAttribute('y', '27')
				->setAttribute('rx', '1.8')
				->setAttribute('ry', '1.8')
				->setAttribute('fill', '#111111')
				->setAttribute('filter', 'url(#shadow-3d)')
				->addClass('clock-hand-h'),
			
			// Minute hand
			(new CTag('rect', true))
				->setAttribute('width', '3.0')
				->setAttribute('height', '36')
				->setAttribute('x', '48.5')
				->setAttribute('y', '14')
				->setAttribute('rx', '1.5')
				->setAttribute('ry', '1.5')
				->setAttribute('fill', '#111111')
				->setAttribute('filter', 'url(#shadow-3d)')
				->addClass('clock-hand-m'),
			
			// Second hand (red color for classic physical look)
			(new CTag('rect', true))
				->setAttribute('width', '1.0')
				->setAttribute('height', '45')
				->setAttribute('x', '49.5')
				->setAttribute('y', '5')
				->setAttribute('fill', '#cc1111')
				->setAttribute('filter', 'url(#shadow-3d)')
				->addClass('clock-hand-s'),
				
			// Central Pin/Cap
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '4.0')
				->setAttribute('fill', '#222222')
				->setAttribute('filter', 'url(#shadow-3d)'),
			(new CTag('circle', true))
				->setAttribute('cx', '50')
				->setAttribute('cy', '50')
				->setAttribute('r', '1.5')
				->setAttribute('fill', '#cc1111')
		];
	}

	private function build() {
		$clock = (new CTag('svg', true))
			->addItem($this->makeClockDefs())
			->addItem($this->makeClockFace())
			->addItem($this->makeClockHands())
			->setAttribute('xmlns', 'http://www.w3.org/2000/svg')
			->setAttribute('viewBox', '0 0 100 100')
			->addClass(ZBX_STYLE_CLOCK_SVG);

		if ($this->width !== null && $this->height !== null) {
			$clock->setAttribute('style', 'width: '.$this->width.'px; height:'.$this->height.'px;');
		}

		if (!$this->is_enabled) {
			$clock->addClass(ZBX_STYLE_DISABLED);
		}

		$this->addItem($clock);
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
