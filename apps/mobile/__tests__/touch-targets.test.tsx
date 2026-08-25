import React from 'react';
import { render } from '@testing-library/react-native';
import { Button } from '../src/shared/components/Button';
import { MIN_TOUCH_TARGET } from '@dominion/tooling/design-tokens';

describe('Accessibility: Touch Targets', () => {
  it('Button enforces minimum touch target height', () => {
    const { getByTestId } = render(<Button testID="test-button" title="Tap Me" />);
    const pressable = getByTestId('test-button');
    
    // The Button component uses a render prop for Pressable, returning a Box (View)
    // In React Test Renderer, functional children are evaluated and inserted as children
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBox = pressable.children[0] as any;
    
    const style = innerBox.props.style;
    
    // React Native testing library doesn't always flatten styles automatically in the props tree
    // We do a simple flatten for arrays
    const flattenedStyle = Array.isArray(style) ? Object.assign({}, ...style.flat(Infinity)) : style;
    
    expect(flattenedStyle.minHeight).toBe(MIN_TOUCH_TARGET);
  });
});
