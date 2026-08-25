import React, { forwardRef } from 'react';
import GorhomBottomSheet, { type BottomSheetProps as BaseProps } from '@gorhom/bottom-sheet';
import { useTheme } from '@/theme';

export type BottomSheetProps = BaseProps;

export const BottomSheet = forwardRef<GorhomBottomSheet, BottomSheetProps>(
  ({ backgroundStyle, handleIndicatorStyle, ...rest }, ref) => {
    const theme = useTheme();

    return (
      <GorhomBottomSheet
        ref={ref}
        backgroundStyle={[
          { backgroundColor: theme.color.surface.raised },
          backgroundStyle,
        ]}
        handleIndicatorStyle={[
          { backgroundColor: theme.color.border.subtle },
          handleIndicatorStyle,
        ]}
        {...rest}
      />
    );
  }
);
BottomSheet.displayName = 'BottomSheet';
