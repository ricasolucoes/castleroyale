import { View, type ViewProps } from 'react-native';
import { useTheme } from '@/theme';

export type SpacingToken = keyof ReturnType<typeof useTheme>['spacing'];
export type RadiusToken = keyof ReturnType<typeof useTheme>['radius'];

export type BoxProps = ViewProps & {
  p?: SpacingToken;
  px?: SpacingToken;
  py?: SpacingToken;
  pt?: SpacingToken;
  pb?: SpacingToken;
  pl?: SpacingToken;
  pr?: SpacingToken;
  m?: SpacingToken;
  mx?: SpacingToken;
  my?: SpacingToken;
  mt?: SpacingToken;
  mb?: SpacingToken;
  ml?: SpacingToken;
  mr?: SpacingToken;
  backgroundColor?: string;
  borderRadius?: RadiusToken;
};

export function Box({
  style,
  p, px, py, pt, pb, pl, pr,
  m, mx, my, mt, mb, ml, mr,
  backgroundColor,
  borderRadius,
  ...rest
}: BoxProps) {
  const theme = useTheme();
  
  return (
    <View 
      style={[
        p ? { padding: theme.spacing[p] } : undefined,
        px ? { paddingHorizontal: theme.spacing[px] } : undefined,
        py ? { paddingVertical: theme.spacing[py] } : undefined,
        pt ? { paddingTop: theme.spacing[pt] } : undefined,
        pb ? { paddingBottom: theme.spacing[pb] } : undefined,
        pl ? { paddingLeft: theme.spacing[pl] } : undefined,
        pr ? { paddingRight: theme.spacing[pr] } : undefined,
        m ? { margin: theme.spacing[m] } : undefined,
        mx ? { marginHorizontal: theme.spacing[mx] } : undefined,
        my ? { marginVertical: theme.spacing[my] } : undefined,
        mt ? { marginTop: theme.spacing[mt] } : undefined,
        mb ? { marginBottom: theme.spacing[mb] } : undefined,
        ml ? { marginLeft: theme.spacing[ml] } : undefined,
        mr ? { marginRight: theme.spacing[mr] } : undefined,
        backgroundColor ? { backgroundColor } : undefined,
        borderRadius ? { borderRadius: theme.radius[borderRadius] } : undefined,
        style
      ]} 
      {...rest} 
    />
  );
}
