import { Text as RNText, type TextProps as RNTextProps, type TextStyle } from 'react-native';
import { useTheme } from '@/theme';

export type TextVariant = 'display' | 'title' | 'heading' | 'body' | 'label' | 'caption' | 'numeric';

export type TextProps = RNTextProps & {
  variant?: TextVariant;
  color?: string; // allow overriding color from theme explicitly if needed
};

export function Text({ style, variant = 'body', color, ...rest }: TextProps) {
  const theme = useTheme();
  const typo = theme.typography[variant];
  
  return (
    <RNText 
      style={[
        { 
          color: color || theme.color.text.primary,
          fontSize: typo.size,
          fontWeight: typo.weight as TextStyle['fontWeight'],
          lineHeight: typo.lineHeight,
        },
        // @ts-expect-error variant isn't perfectly mapped in rn types sometimes
        typo.variant ? { fontVariant: [typo.variant] } : undefined,
        style
      ]} 
      {...rest} 
    />
  );
}
