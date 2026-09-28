import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

import { IntroCinematicScreen } from '../src/features/narrative/components/IntroCinematicScreen';

jest.mock('../src/i18n/useTranslation', () => ({
  useTranslation: () => ({
    t: (key: string, params?: Record<string, string | number>) =>
      params ? `${key}:${JSON.stringify(params)}` : key,
  }),
}));

jest.mock('expo-router', () => ({
  router: {
    canGoBack: jest.fn(() => false),
    back: jest.fn(),
    replace: jest.fn(),
  },
}));

describe('IntroCinematicScreen', () => {
  it('renders initial chapter with narrative title, body, and next button', () => {
    const { getByText } = render(<IntroCinematicScreen />);

    expect(getByText('intro.chapter1_title')).toBeTruthy();
    expect(getByText('intro.chapter1_body')).toBeTruthy();
    expect(getByText('intro.next')).toBeTruthy();
  });

  it('steps forward through chapters and reaches the final start journey CTA', () => {
    const { getByText, queryByText } = render(<IntroCinematicScreen />);

    // Chapter 1 -> Chapter 2
    fireEvent.press(getByText('intro.next'));
    expect(getByText('intro.chapter2_title')).toBeTruthy();
    expect(getByText('intro.previous')).toBeTruthy();

    // Chapter 2 -> Chapter 3
    fireEvent.press(getByText('intro.next'));
    expect(getByText('intro.chapter3_title')).toBeTruthy();

    // Chapter 3 -> Chapter 4 (Final)
    fireEvent.press(getByText('intro.next'));
    expect(getByText('intro.chapter4_title')).toBeTruthy();
    expect(getByText('intro.start_journey')).toBeTruthy();
    expect(queryByText('intro.next')).toBeNull();
  });

  it('can step backwards with the previous button', () => {
    const { getByText } = render(<IntroCinematicScreen />);

    fireEvent.press(getByText('intro.next'));
    expect(getByText('intro.chapter2_title')).toBeTruthy();

    fireEvent.press(getByText('intro.previous'));
    expect(getByText('intro.chapter1_title')).toBeTruthy();
  });

  it('calls onComplete when skip button is pressed', () => {
    const onComplete = jest.fn();
    const { getByLabelText } = render(<IntroCinematicScreen onComplete={onComplete} />);

    fireEvent.press(getByLabelText('intro.skip'));
    expect(onComplete).toHaveBeenCalledTimes(1);
  });
});
