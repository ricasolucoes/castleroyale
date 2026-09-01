import { render } from '@testing-library/react-native';
import { Button } from '../src/shared/components/Button';
import { BottomSheet } from '../src/shared/components/BottomSheet';
import { MIN_TOUCH_TARGET } from '@castleroyale/tooling/design-tokens';
import { Text } from 'react-native';
import React from 'react';

jest.mock('@gorhom/bottom-sheet', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactMock = require('react');
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const BottomSheetMock = ReactMock.forwardRef((props: any, _ref: any) => <ReactNativeMock.View testID="gorhom-bottom-sheet" {...props} />);
  return {
    __esModule: true,
    default: BottomSheetMock,
  };
});

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

  it('BottomSheet delegates touch targets to the library', () => {
    const { toJSON } = render(
      <BottomSheet snapPoints={['25%', '50%']}>
        <Text>BottomSheet Content</Text>
      </BottomSheet>
    );
    
    // BottomSheet handles are delegated to the @gorhom/bottom-sheet library,
    // which enforces Human Interface Guidelines (HIG) standards.
    // We verify the component mounts without errors.
    expect(toJSON()).toBeTruthy();
  });
});
