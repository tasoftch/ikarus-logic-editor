<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Ikarus\Logic\Editor;

use Ikarus\Logic\Editor\Component\EditableComponentControlsInterface;
use Ikarus\Logic\Editor\Component\EditableComponentInterface;
use Ikarus\Logic\Editor\Exception\SerializationException;
use Ikarus\Logic\Editor\Localization\LocalizationInterface;
use Ikarus\Logic\Model\Component\ComponentInterface;
use Ikarus\Logic\Model\Component\IdentifiedNodeComponentInterface;
use Ikarus\Logic\Model\Component\Socket\SocketComponentInterface;
use Ikarus\Logic\Model\Component\Socket\Type\TypeInterface;

/**
 * Use this class to create plain representations of the components.
 * This can be used to send to the editor system to load/store and edit projects by the user
 *
 * @package Ikarus\Logic\Editor
 */
abstract class ComponentSerialization
{
    const COMPONENT_NAME_KEY = 'name';
    const COMPONENT_LABEL_KEY = 'label';
    const COMPONENT_MENU_KEY = 'menu';
    const COMPONENT_MENU_LABEL_KEY = 'mlabel';
    const COMPONENT_IDENTIFIERS_KEY = 'identifiers';

    const COMPONENT_CONTROLS_KEY = 'controls';

    const SOCKET_TYPE_KEY = 'type';
    const SOCKET_LABEL_KEY = 'label';
    const SOCKET_MULTIPLE_KEY = 'multiple';

    const OUTPUTS_KEY = 'outputs';
    const INPUTS_KEY = 'inputs';

    const TYPE_NAME_KEY = 'name';
    const TYPE_COMBINATIONS_KEY = 'combines';
    const TYPE_LABEL_KEY = 'label';


    /**
     * @param iterable $types
     * @param callable|NULL $serializer
     * @param LocalizationInterface|NULL $localization
     * @return array|mixed
     * @throws SerializationException
     */
    public static function getSerializedSocketTypes(iterable $types, callable $serializer = NULL, LocalizationInterface $localization = NULL) {
        $serializedTypes = [];
        foreach($types as $type) {
            if($type instanceof TypeInterface) {
                $tname = $type->getName();
                if(!preg_match("/^[a-z_][a-z0-9_]*$/i", $tname)) {
                    $e = new SerializationException("Type name $tname must match pattern ^[a-z_][a-z0-9_]*$");
                    $e->setComponent($type);
                    throw $e;
                }
                $serializedTypes[$tname][ static::TYPE_NAME_KEY ] = $tname;

                $label = $tname;
                if($type instanceof EditableComponentInterface) {
                    $label = $type->getLabel();
                }
                if($localization)
                    $label = $localization->getLocalizedString($label) ?: $label;
                $serializedTypes[$tname][ static::TYPE_LABEL_KEY ] = $label;

                if($cb = $type->getCombinedTypes()) {
                    foreach($cb as $c)
                        $serializedTypes[$tname][ static::TYPE_COMBINATIONS_KEY ][] = $c->getName();
                }
            }
        }

        return is_callable( $serializer ) ? call_user_func($serializer, $serializedTypes) : $serializedTypes;
    }

    /**
     * Converts a component list into a plain representation to send to an editor
     *
     * @param iterable $components
     * @param callable|NULL $serializer
     * @param LocalizationInterface|NULL $localization
     * @return array|mixed
     * @throws SerializationException
     */
    public static function getSerializedComponents(iterable $components, callable $serializer = NULL, LocalizationInterface $localization = NULL) {
        $serializedComponents = [];
        foreach($components as $component) {
            if($component instanceof ComponentInterface) {
                try {
                    $cname = $component->getName();
                    if(strpos($cname, ':') !== false)
                        throw new SerializationException("Invalid component name %s including colon (:)", 0, NULL, $cname);

                    $serializedComponents[$cname][static::COMPONENT_NAME_KEY] = $cname;

                    $identifier = NULL;
                    if($component instanceof IdentifiedNodeComponentInterface)
                        $identifier = $component->getIdentifier();

                    $label = $cname;
                    $menuLabel = $cname;
                    $menu = NULL;

                    if($component instanceof EditableComponentInterface) {
                        $label = $component->getLabel();
                        $menuLabel = $component->getMenuLabel();

                        if($mp = $component->getMenuPath()) {
                            $menuPath = "";
                            foreach(explode("/", $mp) as $menuItem) {
                                if($localization) {
                                    $menuPath .= "$menuItem/";
                                    $m = $localization->getLocalizedString($menuPath);
                                    if($m)
                                        $menuItem = $m;
                                }

                                $menu[] = $menuItem;
                            }
                        }
                    }

                    if($localization) {
                        $label = $localization->getLocalizedString($label) ?: $label;
                        $menuLabel = $localization->getLocalizedString($menuLabel) ?: $menuLabel;
                    }

                    if($identifier) {
                        $serializedComponents[ $cname ][ static::COMPONENT_IDENTIFIERS_KEY ][$identifier] = [
                            static::COMPONENT_LABEL_KEY => $label,
                            static::COMPONENT_MENU_KEY => $menu,
                            static::COMPONENT_MENU_LABEL_KEY => $menuLabel
                        ];
                    } else {
                        $serializedComponents[ $cname ][ static::COMPONENT_LABEL_KEY ] = $label;
                        $serializedComponents[ $cname ][ static::COMPONENT_MENU_LABEL_KEY ] = $menuLabel;
                        $serializedComponents[ $cname ][ static::COMPONENT_MENU_KEY ] = $menu;
                    }

                    if($component instanceof EditableComponentControlsInterface) {
                        $serializedComponents[ $cname ][ static::COMPONENT_CONTROLS_KEY ] = $component->getControlTypeNames();
                    }

                    foreach($component->getOutputSockets() as $socket) {
                        if($socket instanceof SocketComponentInterface) {
                            $sid = $socket->getName();
                            $serializedComponents[ $cname ][ static::OUTPUTS_KEY ][$sid][static::SOCKET_TYPE_KEY] = $socket->getSocketType();
                            $serializedComponents[ $cname ][ static::OUTPUTS_KEY ][$sid][static::SOCKET_MULTIPLE_KEY] = $socket->allowsMultiple();
                            $serializedComponents[ $cname ][ static::OUTPUTS_KEY ][$sid][static::SOCKET_LABEL_KEY] = ($socket instanceof EditableComponentInterface) ? $socket->getLabel() : $sid;
                        }
                    }

                    foreach($component->getInputSockets() as $socket) {
                        if($socket instanceof SocketComponentInterface) {
                            $sid = $socket->getName();
                            $serializedComponents[ $cname ][ static::INPUTS_KEY ][$sid][static::SOCKET_TYPE_KEY] = $socket->getSocketType();
                            $serializedComponents[ $cname ][ static::INPUTS_KEY ][$sid][static::SOCKET_MULTIPLE_KEY] = $socket->allowsMultiple();
                            $serializedComponents[ $cname ][ static::INPUTS_KEY ][$sid][static::SOCKET_LABEL_KEY] = ($socket instanceof EditableComponentInterface) ? $socket->getLabel() : $sid;
                        }
                    }
                } catch (SerializationException $exception) {
                    $exception->setComponent($component);
                    throw $exception;
                }
            }
        }

        return is_callable( $serializer ) ? call_user_func($serializer, $serializedComponents) : $serializedComponents;
    }
}