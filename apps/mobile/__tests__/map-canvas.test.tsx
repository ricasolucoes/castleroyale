import React from 'react';
import { render } from '@testing-library/react-native';
import { MapCanvas } from '../src/features/world/components/MapCanvas';
import { MIN_TOUCH_TARGET } from '@dominion/tooling/design-tokens';

jest.mock('@shopify/react-native-skia', () => {
  const ReactMock = require('react');
  const { View } = require('react-native');
  return {
    Canvas: (props: any) => <View testID="skia-canvas" {...props} />,
    Circle: (props: any) => <View testID="skia-circle" {...props} />,
    Group: (props: any) => <View testID="skia-group" {...props} />,
    Path: (props: any) => <View testID="skia-path" {...props} />,
    Skia: {
      Path: {
        Make: () => ({ addRect: jest.fn() }),
      },
    },
  };
});

jest.mock('react-native-gesture-handler', () => {
  const ReactMock = require('react');
  const { View } = require('react-native');
  return {
    GestureDetector: (props: any) => <View testID="gesture-detector" {...props} />,
    Gesture: {
      Pan: () => ({ onStart: jest.fn().mockReturnThis(), onUpdate: jest.fn().mockReturnThis() }),
      Pinch: () => ({ onStart: jest.fn().mockReturnThis(), onUpdate: jest.fn().mockReturnThis() }),
      Simultaneous: jest.fn(),
    },
  };
});

jest.mock('react-native-reanimated', () => {
  const ReactMock = require('react');
  const createAnimatedComponent = (Component: any) => {
    return ReactMock.forwardRef((props: any, ref: any) => {
      return ReactMock.createElement(Component, { ...props, ref });
    });
  };
  return {
    __esModule: true,
    useSharedValue: jest.fn((init) => ({ value: init })),
    useAnimatedStyle: jest.fn(() => ({})),
    default: {
      createAnimatedComponent,
    },
    createAnimatedComponent,
  };
});

jest.mock('../src/i18n/useTranslation', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));

describe('MapCanvas Component', () => {
  it('exposes exactly one Skia Canvas', () => {
    const { getAllByTestId } = render(
      <MapCanvas
        tiles={[]}
        bounds={{ minX: -1, maxX: 1, minY: -1, maxY: 1 }}
        playerX={0}
        playerY={0}
      />
    );
    expect(getAllByTestId('skia-canvas')).toHaveLength(1);
  });

  it('reset control has a minimum 44pt target', () => {
    const { getByLabelText } = render(
      <MapCanvas
        tiles={[]}
        bounds={{ minX: -1, maxX: 1, minY: -1, maxY: 1 }}
        playerX={0}
        playerY={0}
      />
    );

    // The pressable has the accessibility label
    const pressable = getByLabelText('world.reset_to_city');
    const style = pressable.props.style;
    const flattenedStyle = Array.isArray(style) ? Object.assign({}, ...style.flat(Infinity)) : style;
    
    expect(flattenedStyle.minWidth).toBe(MIN_TOUCH_TARGET);
    expect(flattenedStyle.minHeight).toBe(MIN_TOUCH_TARGET);
  });

  it('updates camera state using isolated shared values rather than React state', () => {
    const useStateSpy = jest.spyOn(React, 'useState');
    render(
      <MapCanvas
        tiles={[]}
        bounds={{ minX: -1, maxX: 1, minY: -1, maxY: 1 }}
        playerX={0}
        playerY={0}
      />
    );
    
    // Check that we are not using useState for zooming or panning
    // Since useTheme, useTranslation might use useState, we just ensure none of them are numeric values that look like camera coordinates
    const stateValues = useStateSpy.mock.calls.map(call => call[0]);
    expect(stateValues).not.toContainEqual(1); // Default zoom
    expect(stateValues).not.toContainEqual(0); // Default translateX/Y
    
    useStateSpy.mockRestore();
  });
});
